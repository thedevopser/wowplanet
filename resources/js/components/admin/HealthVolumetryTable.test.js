import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import HealthVolumetryTable from './HealthVolumetryTable.vue';

const table = (overrides = {}) => ({
    table: 'wow_mounts',
    family: 'catalogue',
    rows: 1_200,
    active: 900,
    without_icon: 12,
    status: 'ok',
    issue: null,
    ...overrides,
});

const mountTable = tables => mountWithPlugins(HealthVolumetryTable, { props: { tables } });

describe('HealthVolumetryTable', () => {
    it('gives the rows of each table with the share of active and iconless ones', async () => {
        const wrapper = await mountTable([table()]);
        const row = wrapper.get('[data-table="wow_mounts"]');

        expect(row.text()).toMatch(/1\s200/u);
        expect(row.get('[data-role="active"]').text()).toBe('75 %');
        expect(row.get('[data-role="without-icon"]').text()).toBe('1 %');
    });

    it('leaves the shares blank for a table that does not carry them', async () => {
        const wrapper = await mountTable([table({ table: 'users', family: 'application', active: null, without_icon: null })]);
        const row = wrapper.get('[data-table="users"]');

        expect(row.get('[data-role="active"]').text()).toBe('—');
        expect(row.get('[data-role="without-icon"]').text()).toBe('—');
    });

    it('does not divide by zero on an empty table, and names the anomaly', async () => {
        const wrapper = await mountTable([table({ rows: 0, active: 0, without_icon: 0, status: 'critical', issue: 'Table du catalogue vide.' })]);
        const row = wrapper.get('[data-table="wow_mounts"]');

        expect(row.get('[data-role="active"]').text()).toBe('—');
        expect(row.text()).toContain('Table du catalogue vide.');
        expect(row.get('[data-status]').text()).toBe('Anomalie');
    });

    it('separates catalogue tables from application tables', async () => {
        const wrapper = await mountTable([table(), table({ table: 'users', family: 'application', active: null, without_icon: null })]);

        expect(wrapper.get('[data-family="catalogue"]').text()).toContain('wow_mounts');
        expect(wrapper.get('[data-family="application"]').text()).toContain('users');
    });
});
