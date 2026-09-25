import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import ImportEntityTable from './ImportEntityTable.vue';

const entities = [
    { stage: 'achievements', label: 'Hauts faits', rows: 8555, imported_at: '2026-09-18T20:00:00+00:00', build: '12.1.0_68914', estimated_api_calls: 8700 },
    { stage: 'quests', label: 'Quêtes', rows: 22055, imported_at: null, build: null, estimated_api_calls: 3000 },
    { stage: 'mounts', label: 'Montures', rows: 1663, imported_at: '2026-09-19T08:30:00+00:00', build: '12.1.0_68914', estimated_api_calls: 5 },
];

const mountTable = (props = {}) => mountWithPlugins(ImportEntityTable, {
    props: { entities, selection: [], disabled: false, ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('ImportEntityTable', () => {
    it('lists every entity with its label', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toContain('Hauts faits');
        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('Montures');
    });

    it('shows how many rows an entity holds, grouped for readability', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toMatch(/22\s055/);
    });

    it('shows the build of the last successful import', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toContain('12.1.0_68914');
    });

    it('says an entity has never been imported rather than showing an empty date', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toContain('Jamais importée');
    });

    it('announces the order of magnitude of a forced import', async () => {
        const wrapper = await mountTable();

        expect(wrapper.text()).toMatch(/~8\s700 appels/);
    });

    it('checks the entities of the current selection', async () => {
        const wrapper = await mountTable({ selection: ['quests'] });

        const checked = wrapper.findAll('input[type="checkbox"]').filter(input => input.element.checked);

        expect(checked).toHaveLength(1);
        expect(checked[0].attributes('value')).toBe('quests');
    });

    it('asks for an entity to be added to the selection', async () => {
        const wrapper = await mountTable();

        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);

        expect(wrapper.emitted('update:selection').at(-1)).toEqual([['quests']]);
    });

    it('asks for an entity to be dropped from the selection', async () => {
        const wrapper = await mountTable({ selection: ['achievements', 'quests'] });

        await wrapper.findAll('input[type="checkbox"]')[0].setValue(false);

        expect(wrapper.emitted('update:selection').at(-1)).toEqual([['quests']]);
    });

    it('locks the boxes while an import runs', async () => {
        const wrapper = await mountTable({ disabled: true });

        expect(wrapper.findAll('input[type="checkbox"]').every(input => input.attributes('disabled') !== undefined)).toBe(true);
    });
});
