import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import ImportHistoryComparisonTable from './ImportHistoryComparisonTable.vue';

const side = (overrides = {}) => ({
    stage: 'mounts', label: 'Montures', status: 'completed', created: 1, updated: 2, deleted: 0,
    api_calls: 5, duration_ms: 1_000, rows_after: 1_000, error: null, ...overrides,
});

const stage = (overrides = {}) => ({
    stage: 'mounts',
    label: 'Montures',
    older_rows: 1_000,
    newer_rows: 1_100,
    delta: 100,
    shrunk: false,
    older: side(),
    newer: side({ rows_after: 1_100, api_calls: 7 }),
    ...overrides,
});

const mountTable = stages => mountWithPlugins(ImportHistoryComparisonTable, { props: { stages } });

describe('ImportHistoryComparisonTable', () => {
    it('says so when the two imports share no entity', async () => {
        const wrapper = await mountTable([]);

        expect(wrapper.get('[data-role="nothing-shared"]').text()).toContain('Aucune entité commune');
    });

    it('sets the two volumes of each shared entity side by side, with the change between them', async () => {
        const row = (await mountTable([stage()])).get('[data-stage="mounts"]');

        expect(row.get('[data-role="older"]').text().replace(/\s/gu, ' ')).toContain('1 000');
        expect(row.get('[data-role="newer"]').text().replace(/\s/gu, ' ')).toContain('1 100');
        expect(row.get('[data-role="delta"]').text().replace(/\s/gu, ' ')).toBe('+100');
    });

    it('flags an entity whose volume fell', async () => {
        const row = (await mountTable([stage({ newer_rows: 10, delta: -990, shrunk: true })])).get('[data-stage="mounts"]');

        expect(row.get('[data-alert="shrunk"]').text()).toContain('Chute de volume');
    });

    it('shows a blank for a volume one of the imports did not measure', async () => {
        const row = (await mountTable([stage({ older_rows: null, delta: null })])).get('[data-stage="mounts"]');

        expect(row.get('[data-role="older"]').text()).toContain('—');
        expect(row.get('[data-role="delta"]').text()).toBe('—');
    });
});
