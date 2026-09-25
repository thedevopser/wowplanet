import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import ImportHistoryStepTable from './ImportHistoryStepTable.vue';

const step = (overrides = {}) => ({
    stage: 'mounts',
    label: 'Montures',
    status: 'completed',
    created: 12,
    updated: 3,
    deleted: 1,
    api_calls: 5,
    duration_ms: 1_200,
    rows_after: 1_663,
    error: null,
    previous_rows: 1_650,
    previous_job_id: 'job-0',
    delta: 13,
    shrunk: false,
    ...overrides,
});

const rowOf = async (steps) => (await mountWithPlugins(ImportHistoryStepTable, { props: { steps } })).get(`[data-stage="${steps[0].stage}"]`);

describe('ImportHistoryStepTable', () => {
    it('gives what each stage created, updated and deleted, what it cost and how long it took', async () => {
        const row = await rowOf([step()]);

        expect(row.get('[data-role="rows"]').text()).toBe('+12 / ~3 / −1');
        expect(row.text()).toContain('5');
        expect(row.text()).toContain('1 s');
        expect(row.get('[data-status]').text()).toBe('Terminé');
    });

    it('sets the volume against the previous import of the stage', async () => {
        const row = await rowOf([step()]);

        expect(row.get('[data-role="volume"]').text().replace(/\s/gu, ' ')).toContain('1 663');
        expect(row.get('[data-role="delta"]').text().replace(/\s/gu, ' ')).toBe('+13 depuis 1 650');
    });

    it('says a stage has no earlier import to be compared with', async () => {
        const row = await rowOf([step({ previous_rows: null, previous_job_id: null, delta: null })]);

        expect(row.get('[data-role="delta"]').text()).toBe('premier relevé');
    });

    it('flags a fall in volume rather than leaving two numbers to compare', async () => {
        const row = await rowOf([step({ rows_after: 600, previous_rows: 1_650, delta: -1_050, shrunk: true })]);

        expect(row.get('[data-alert="shrunk"]').text()).toContain('Chute de volume');
    });

    it('says why a stage failed', async () => {
        const row = await rowOf([step({ status: 'failed', error: 'mount index unavailable' })]);

        expect(row.text()).toContain('mount index unavailable');
    });

    it('leaves the volume blank for a stage that has none of its own tables', async () => {
        const row = await rowOf([step({ stage: 'reference', label: 'Socle de référence', rows_after: null, previous_rows: null, delta: null })]);

        expect(row.get('[data-role="volume"]').text()).toBe('—');
        expect(row.find('[data-role="delta"]').exists()).toBe(false);
    });
});
