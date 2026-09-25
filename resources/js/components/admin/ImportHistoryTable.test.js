import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: {} }),
}));

import { mountWithPlugins } from '../../tests/helpers';
import ImportHistoryTable from './ImportHistoryTable.vue';

const entry = (overrides = {}) => ({
    job_id: 'job-1',
    trigger: '12345',
    mode: 'forced',
    status: 'completed',
    started_at: '2026-09-22T10:00:00+00:00',
    finished_at: '2026-09-22T10:05:08+00:00',
    budget_used: 15_457,
    stages: [{ stage: 'mounts', label: 'Montures' }, { stage: 'pets', label: 'Mascottes' }],
    shrunk: [],
    ...overrides,
});

const mountTable = (entries = [entry()], selected = []) => mountWithPlugins(ImportHistoryTable, { props: { entries, selected } });

describe('ImportHistoryTable', () => {
    it('says so when no import has been recorded yet', async () => {
        const wrapper = await mountTable([]);

        expect(wrapper.get('[data-role="empty-history"]').text()).toContain('Aucun import');
    });

    it('gives each import with who launched it, how, what it covered and how long it took', async () => {
        const row = (await mountTable()).get('[data-entry="job-1"]');

        expect(row.text()).toContain('12345');
        expect(row.text()).toContain('Forcé');
        expect(row.text()).toContain('Montures, Mascottes');
        expect(row.text()).toContain('5 min 08 s');
        expect(row.get('[data-status]').text()).toBe('Terminé');
    });

    it('links each import to its report', async () => {
        const row = (await mountTable()).get('[data-entry="job-1"]');

        expect(row.get('a').attributes('href')).toBe('/admin/history/job-1');
    });

    it('names an import launched from the console as such', async () => {
        const row = (await mountTable([entry({ trigger: 'console', mode: 'incremental' })])).get('[data-entry="job-1"]');

        expect(row.text()).toContain('Console');
        expect(row.text()).toContain('Incrémental');
    });

    it('shows no duration for an import that never finished', async () => {
        const row = (await mountTable([entry({ status: 'abandoned', finished_at: null })])).get('[data-entry="job-1"]');

        expect(row.get('[data-role="duration"]').text()).toBe('—');
    });

    it('flags a fall in volume, naming the entities that fell', async () => {
        const row = (await mountTable([entry({ shrunk: [{ stage: 'mounts', label: 'Montures' }] })])).get('[data-entry="job-1"]');

        expect(row.get('[data-alert="shrunk"]').text()).toContain('Chute de volume : Montures');
    });

    it('selects imports for comparison', async () => {
        const wrapper = await mountTable([entry(), entry({ job_id: 'job-2' })]);

        await wrapper.get('[data-entry="job-2"] input[type="checkbox"]').setValue(true);

        expect(wrapper.emitted('update:selected')).toEqual([[['job-2']]]);
    });

    it('drops an import from the selection', async () => {
        const wrapper = await mountTable([entry()], ['job-1']);

        await wrapper.get('[data-entry="job-1"] input[type="checkbox"]').setValue(false);

        expect(wrapper.emitted('update:selected')).toEqual([[[]]]);
    });

    it('lets no third import in once two are selected', async () => {
        const wrapper = await mountTable([entry(), entry({ job_id: 'job-2' }), entry({ job_id: 'job-3' })], ['job-1', 'job-2']);

        expect(wrapper.get('[data-entry="job-3"] input[type="checkbox"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-entry="job-1"] input[type="checkbox"]').attributes('disabled')).toBeUndefined();
    });
});
