import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';

const visit = vi.fn();
const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/taxonomy', props: {} }),
    router: { visit: (...args) => visit(...args), reload: (...args) => reload(...args) },
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminTaxonomyPage from './AdminTaxonomyPage.vue';
import { expectNoAxeViolations } from '../tests/axe';

vi.mock('axios');

const entry = (overrides = {}) => ({ id: 7, name: 'Loup gris', pending_source: 'Trading Post', ...overrides });

const props = (overrides = {}) => ({
    entity: 'mount',
    search: '',
    counts: {
        mount: { pending: 2, catalogue: 1663 },
        pet: { pending: 680, catalogue: 2177 },
        decor: { pending: 0, catalogue: 2131 },
    },
    entries: [entry(), entry({ id: 8, name: 'Étalon blanc', pending_source: null })],
    curatedCounts: { mount: 1661, pet: 1497, decor: 2131 },
    matched: 2,
    perPage: 50,
    vocabulary: { categories: ['Racial', 'Professions'], sources: ['Human', 'Fishing'] },
    snapshot: { path: 'database/data/collection_taxonomy.csv', entries: 5242, in_step: true, missing_in_base: 0, missing_in_file: 0, differing: 0 },
    ...overrides,
});

const mountPage = (overrides = {}) => mountWithPlugins(AdminTaxonomyPage, { props: props(overrides) });

const buttonLabelled = (wrapper, label) => wrapper.findAll('button').find(b => b.text() === label);

beforeEach(() => vi.clearAllMocks());

describe('AdminTaxonomyPage', () => {
    it('says what each collection has left to arbitrate', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-tab="pet"]').text()).toContain('680');
    });

    it('lists the pending entries of the current collection', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Loup gris');
        expect(wrapper.text()).toContain('Étalon blanc');
    });

    it('switches collection by asking the server, since the list comes from there', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-tab="pet"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=pet', expect.anything());
    });

    it('says how many entries matched when the page shows only a slice', async () => {
        const wrapper = await mountPage({ matched: 680, entries: [entry()] });

        expect(wrapper.text()).toContain('680');
    });

    it('refuses to arbitrate while nothing is selected', async () => {
        const wrapper = await mountPage();

        expect(buttonLabelled(wrapper, 'Ranger la sélection').attributes('disabled')).toBeDefined();
    });

    it('files the selection under the category and source that were typed', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { arbitrated: 1, snapshot: props().snapshot } });

        const wrapper = await mountPage();
        await wrapper.get('[data-action="select-entry"]').setValue(true);
        await wrapper.get('[data-field="category"] input').setValue('Racial');
        await wrapper.get('[data-field="source"] input').setValue('Human');
        await buttonLabelled(wrapper, 'Ranger la sélection').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/taxonomy/arbitrate', {
            entity: 'mount',
            entries: [7],
            category: 'Racial',
            source: 'Human',
        });
    });

    it('files a selection nowhere on purpose, which is how an entry stops coming back', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { arbitrated: 1, snapshot: props().snapshot } });

        const wrapper = await mountPage();
        await wrapper.get('[data-action="select-entry"]').setValue(true);
        await buttonLabelled(wrapper, 'Ranger nulle part').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/taxonomy/arbitrate', {
            entity: 'mount',
            entries: [7],
            category: null,
            source: null,
        });
    });

    it('warns that a category the collection does not know yet becomes a new tab', async () => {
        const wrapper = await mountPage();
        await wrapper.get('[data-field="category"] input').setValue('Nouvelle catégorie');
        await flushPromises();

        expect(wrapper.get('[data-alert="new-category"]').text()).toContain('onglet');
    });

    it('stays quiet when the category is one the collection already uses', async () => {
        const wrapper = await mountPage();
        await wrapper.get('[data-field="category"] input').setValue('Racial');

        expect(wrapper.find('[data-alert="new-category"]').exists()).toBe(false);
    });

    it('reads the page again once an arbitration is through, so the list stops lying', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { arbitrated: 1, snapshot: props().snapshot } });

        const wrapper = await mountPage();
        await wrapper.get('[data-action="select-entry"]').setValue(true);
        await buttonLabelled(wrapper, 'Ranger nulle part').trigger('click');
        await vi.waitFor(() => expect(reload).toHaveBeenCalled());
    });

    it('says how many entries it filed', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { arbitrated: 2, snapshot: props().snapshot } });

        const wrapper = await mountPage();
        await wrapper.get('[data-action="select-all"]').setValue(true);
        await buttonLabelled(wrapper, 'Ranger nulle part').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('2 entrées rangées'));
    });

    it('says why the server refused an arbitration instead of staying silent', async () => {
        axios.post = vi.fn().mockRejectedValue({
            response: { data: { message: 'Certaines entrées ne sont pas au catalogue de cette collection.' } },
        });

        const wrapper = await mountPage();
        await wrapper.get('[data-action="select-entry"]').setValue(true);
        await buttonLabelled(wrapper, 'Ranger nulle part').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('ne sont pas au catalogue'));
    });

    it('names the file to commit, since an arbitration only lives in the database until then', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="snapshot"]').text()).toContain('database/data/collection_taxonomy.csv');
    });

    it('flags a snapshot that has drifted from the database', async () => {
        const wrapper = await mountPage({ snapshot: { path: 'database/data/collection_taxonomy.csv', entries: 5242, in_step: false, missing_in_base: 0, missing_in_file: 1, differing: 0 } });

        expect(wrapper.get('[data-alert="missing-in-file"]').exists()).toBe(true);
    });

    it('stays quiet about drift when the snapshot is in step', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-alert="missing-in-file"]').exists()).toBe(false);
        expect(wrapper.find('[data-alert="missing-in-base"]').exists()).toBe(false);
    });

    it('suggests the known categories and sources in themed lists, not in native ones', async () => {
        const wrapper = await mountPage();
        const fields = wrapper.findAllComponents({ name: 'Combobox' });

        expect(fields.map((field) => field.props('label'))).toEqual(['Catégorie', 'Source']);
        expect(fields.map((field) => field.props('options'))).toEqual([['Racial', 'Professions'], ['Human', 'Fishing']]);
        expect(wrapper.find('datalist').exists()).toBe(false);
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage();

        await expectNoAxeViolations(wrapper.element);
    });
});

const curatedEntry = (overrides = {}) => ({ id: 7, name: 'Loup gris', category: 'Other', source: 'Drop', ...overrides });

const curatedProps = (overrides = {}) => ({
    mode: 'curated',
    entries: [curatedEntry(), curatedEntry({ id: 8, name: 'Étalon blanc', category: 'Legion', source: 'Class Hall' })],
    category: null,
    categories: [
        { value: 'Legion', category: 'Legion', entries: 120 },
        { value: 'Other', category: 'Other', entries: 42 },
        { value: '__none__', category: null, entries: 3 },
    ],
    ...overrides,
});

const selectField = (wrapper) => wrapper.findAllComponents({ name: 'Select' }).find((field) => field.props('label') === 'Catégorie actuelle');

describe('AdminTaxonomyPage, mode toggle', () => {
    it('tells both sides of the toggle with the size of the current collection', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-mode="pending"]').text()).toBe('À arbitrer (2)');
        expect(wrapper.get('[data-mode="curated"]').text()).toBe(`Déjà rangées (${(1661).toLocaleString('fr-FR')})`);
    });

    it('opens on the entries to arbitrate', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-mode="pending"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-mode="curated"]').attributes('aria-pressed')).toBe('false');
        expect(wrapper.find('[data-role="pending-source"]').exists()).toBe(true);
    });

    it('switches to the ranked entries through the address, so a link or a reload lands there again', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-mode="curated"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=mount&mode=curated', expect.anything());
    });

    it('switches back to the entries to arbitrate without a mode in the address', async () => {
        const wrapper = await mountPage(curatedProps());

        await wrapper.get('[data-mode="pending"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=mount', expect.anything());
    });

    it('keeps the mode when the collection changes', async () => {
        const wrapper = await mountPage(curatedProps());

        await wrapper.get('[data-tab="pet"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=pet&mode=curated', expect.anything());
    });

    it('empties the selection when the mode or the collection changes', async () => {
        const wrapper = await mountPage(curatedProps());
        await wrapper.get('[data-action="select-entry"]').setValue(true);

        await wrapper.get('[data-mode="pending"]').trigger('click');

        expect(wrapper.get('#file-heading').text()).toContain('0 entrée');
    });
});

describe('AdminTaxonomyPage, ranked entries', () => {
    it('lists the ranked entries with their current category and source', async () => {
        const wrapper = await mountPage(curatedProps());

        expect(wrapper.findAll('[data-role="category"]').map((cell) => cell.text())).toEqual(['Other', 'Legion']);
        expect(wrapper.find('[data-role="pending-source"]').exists()).toBe(false);
    });

    it('says how many ranked entries matched', async () => {
        const wrapper = await mountPage(curatedProps({ matched: 42 }));

        expect(wrapper.text()).toContain('42 rangées');
    });

    it('offers every category of the collection as a filter, with its size, the uncategorised ones included', async () => {
        const wrapper = await mountPage(curatedProps());

        expect(selectField(wrapper).props('options')).toEqual([
            { value: '__all__', label: 'Toutes les catégories' },
            { value: 'Legion', label: 'Legion', hint: '120' },
            { value: 'Other', label: 'Other', hint: '42' },
            { value: '__none__', label: 'Sans catégorie', hint: '3' },
        ]);
        expect(selectField(wrapper).props('modelValue')).toBe('__all__');
    });

    it('shows the active filter', async () => {
        const wrapper = await mountPage(curatedProps({ category: 'Other' }));

        expect(selectField(wrapper).props('modelValue')).toBe('Other');
    });

    it('filters on a category by asking the server, keeping the search', async () => {
        const wrapper = await mountPage(curatedProps({ search: 'loup' }));

        selectField(wrapper).vm.$emit('update:modelValue', 'Other');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=mount&mode=curated&search=loup&category=Other', expect.anything());
    });

    it('drops the filter when every category is asked for', async () => {
        const wrapper = await mountPage(curatedProps({ category: 'Other' }));

        selectField(wrapper).vm.$emit('update:modelValue', '__all__');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=mount&mode=curated', expect.anything());
    });

    it('searches within the mode and the filter', async () => {
        const wrapper = await mountPage(curatedProps({ category: 'Other' }));

        await wrapper.get('input[type="search"]').setValue('loup');
        await buttonLabelled(wrapper, 'Chercher').trigger('click');

        expect(visit).toHaveBeenCalledWith('/admin/taxonomy?entity=mount&mode=curated&search=loup&category=Other', expect.anything());
    });

    it('fills the fields with the current ranking when a single entry is ticked', async () => {
        const wrapper = await mountPage(curatedProps());

        await wrapper.findAll('[data-action="select-entry"]')[1].setValue(true);

        expect(wrapper.get('[data-field="category"] input').element.value).toBe('Legion');
        expect(wrapper.get('[data-field="source"] input').element.value).toBe('Class Hall');
    });

    it('leaves the fields empty when several entries are ticked, since they may not share a ranking', async () => {
        const wrapper = await mountPage(curatedProps());

        await wrapper.get('[data-action="select-all"]').setValue(true);

        expect(wrapper.get('[data-field="category"] input').element.value).toBe('');
        expect(wrapper.get('[data-field="source"] input').element.value).toBe('');
    });

    it('fills an entry ranked nowhere with empty fields', async () => {
        const wrapper = await mountPage(curatedProps({ entries: [curatedEntry({ category: null, source: null })] }));

        await wrapper.get('[data-action="select-entry"]').setValue(true);

        expect(wrapper.get('[data-field="category"] input').element.value).toBe('');
    });

    it('reassigns the selection through the arbitration endpoint', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { arbitrated: 1, snapshot: props().snapshot } });

        const wrapper = await mountPage(curatedProps());
        await wrapper.get('[data-action="select-entry"]').setValue(true);
        await wrapper.get('[data-field="category"] input').setValue('Legion');
        await buttonLabelled(wrapper, 'Réaffecter').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/taxonomy/arbitrate', {
            entity: 'mount',
            entries: [7],
            category: 'Legion',
            source: 'Drop',
        });
    });

    it('says how many entries it reassigned, and reads the list, the counters and the snapshot again', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { arbitrated: 2, snapshot: props().snapshot } });

        const wrapper = await mountPage(curatedProps());
        await wrapper.get('[data-action="select-all"]').setValue(true);
        await wrapper.get('[data-field="category"] input').setValue('Legion');
        await buttonLabelled(wrapper, 'Réaffecter').trigger('click');

        await vi.waitFor(() => expect(wrapper.text()).toContain('2 entrées réaffectées'));
        expect(reload).toHaveBeenCalledWith({
            only: ['entries', 'counts', 'curatedCounts', 'matched', 'categories', 'vocabulary', 'snapshot'],
        });
    });

    it('keeps the selection when the server refuses, and says why', async () => {
        axios.post = vi.fn().mockRejectedValue({
            response: { data: { message: 'Certaines entrées ne sont pas au catalogue de cette collection (mount) : 7.' } },
        });

        const wrapper = await mountPage(curatedProps());
        await wrapper.get('[data-action="select-entry"]').setValue(true);
        await buttonLabelled(wrapper, 'Réaffecter').trigger('click');

        await vi.waitFor(() => expect(wrapper.get('[role="alert"]').text()).toContain('ne sont pas au catalogue'));
        expect(wrapper.get('[data-action="select-entry"]').element.checked).toBe(true);
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage(curatedProps());

        await expectNoAxeViolations(wrapper.element);
    });
});
