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
