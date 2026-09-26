import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import CuratedTaxonomyTable from './CuratedTaxonomyTable.vue';
import { expectNoAxeViolations } from '../../tests/axe';

const entry = (overrides = {}) => ({
    id: 7,
    name: 'Loup gris',
    category: 'Other',
    source: 'Drop',
    ...overrides,
});

const mountTable = (entries = [entry()], props = {}) => mountWithPlugins(CuratedTaxonomyTable, {
    props: { entries, selected: [], disabled: false, ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('CuratedTaxonomyTable', () => {
    it('names each entry with its identifier', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toContain('Loup gris');
        expect(wrapper.text()).toContain('7');
    });

    it('shows the current category and source, which is what a reassignment corrects', async () => {
        const wrapper = await mountTable();

        expect(wrapper.get('[data-role="category"]').text()).toBe('Other');
        expect(wrapper.get('[data-role="source"]').text()).toBe('Drop');
    });

    it('shows a dash for an entry ranked nowhere rather than a blank', async () => {
        const wrapper = await mountTable([entry({ category: null, source: null })]);

        expect(wrapper.get('[data-role="category"]').text()).toBe('—');
        expect(wrapper.get('[data-role="source"]').text()).toBe('—');
    });

    it('selects an entry by its identifier', async () => {
        const wrapper = await mountTable();

        await wrapper.get('[data-action="select-entry"]').setValue(true);

        expect(wrapper.emitted('update:selected')).toEqual([[[7]]]);
    });

    it('drops an entry from the selection when it is unticked', async () => {
        const wrapper = await mountTable([entry()], { selected: [7] });

        await wrapper.get('[data-action="select-entry"]').setValue(false);

        expect(wrapper.emitted('update:selected')).toEqual([[[]]]);
    });

    it('selects the whole page at once, which is how a series gets corrected', async () => {
        const wrapper = await mountTable([entry(), entry({ id: 8, name: 'Étalon blanc' })]);

        await wrapper.get('[data-action="select-all"]').setValue(true);

        expect(wrapper.emitted('update:selected')).toEqual([[[7, 8]]]);
    });

    it('clears the whole page at once too', async () => {
        const wrapper = await mountTable([entry(), entry({ id: 8 })], { selected: [7, 8] });

        await wrapper.get('[data-action="select-all"]').setValue(false);

        expect(wrapper.emitted('update:selected')).toEqual([[[]]]);
    });

    it('offers nothing while a reassignment is in flight', async () => {
        const wrapper = await mountTable([entry()], { disabled: true });

        expect(wrapper.get('[data-action="select-entry"]').attributes('disabled')).toBeDefined();
    });

    it('says nothing matched rather than showing a bare table', async () => {
        const wrapper = await mountTable([]);

        expect(wrapper.text()).toContain('Aucune entrée rangée');
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountTable([entry(), entry({ id: 8, category: null, source: null })]);

        await expectNoAxeViolations(wrapper.element);
    });
});
