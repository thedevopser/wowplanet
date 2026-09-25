import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import PendingTaxonomyTable from './PendingTaxonomyTable.vue';

const entry = (overrides = {}) => ({
    id: 7,
    name: 'Loup gris',
    pending_source: 'Trading Post',
    ...overrides,
});

const mountTable = (entries = [entry()], props = {}) => mountWithPlugins(PendingTaxonomyTable, {
    props: { entries, selected: [], disabled: false, ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('PendingTaxonomyTable', () => {
    it('names each entry with its identifier', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toContain('Loup gris');
        expect(wrapper.text()).toContain('7');
    });

    it('shows the waiting value the importer left, which orients the arbitration', async () => {
        const wrapper = await mountTable();

        expect(wrapper.get('[data-role="pending-source"]').text()).toContain('Trading Post');
    });

    it('says an entry arrived with no waiting value rather than showing a blank', async () => {
        const wrapper = await mountTable([entry({ pending_source: null })]);

        expect(wrapper.get('[data-role="pending-source"]').text()).toContain('—');
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

    it('selects the whole page at once, which is how a batch gets ranked', async () => {
        const wrapper = await mountTable([entry(), entry({ id: 8, name: 'Étalon blanc' })]);

        await wrapper.get('[data-action="select-all"]').setValue(true);

        expect(wrapper.emitted('update:selected')).toEqual([[[7, 8]]]);
    });

    it('clears the whole page at once too', async () => {
        const wrapper = await mountTable([entry(), entry({ id: 8 })], { selected: [7, 8] });

        await wrapper.get('[data-action="select-all"]').setValue(false);

        expect(wrapper.emitted('update:selected')).toEqual([[[]]]);
    });

    it('offers nothing while an arbitration is in flight', async () => {
        const wrapper = await mountTable([entry()], { disabled: true });

        expect(wrapper.get('[data-action="select-entry"]').attributes('disabled')).toBeDefined();
    });

    it('says the collection is fully ranked rather than showing a bare table', async () => {
        const wrapper = await mountTable([]);

        expect(wrapper.text()).toContain('Rien à arbitrer');
    });
});
