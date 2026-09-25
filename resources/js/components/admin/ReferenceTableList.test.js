import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import ReferenceTableList from './ReferenceTableList.vue';

const table = (overrides = {}) => ({
    source: 'Faction',
    table: 'wow_ref_faction',
    rows: 868,
    build: '12.1.0.69875',
    loaded_at: '2026-09-19T19:38:50+00:00',
    previous_rows: 860,
    delta: 8,
    is_empty: false,
    has_shrunk: false,
    is_stale: false,
    ...overrides,
});

const mountList = (tables = [table()], props = {}) => mountWithPlugins(ReferenceTableList, {
    props: { tables, liveBuild: '12.1.0.69875', disabled: false, ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('ReferenceTableList', () => {
    it('names each table with what it weighs and when it was loaded', async () => {
        const wrapper = await mountList();

        expect(wrapper.text()).toContain('Faction');
        expect(wrapper.text()).toMatch(/868/);
        expect(wrapper.text()).toContain('12.1.0.69875');
    });

    it('shows the comparison with the previous load', async () => {
        const wrapper = await mountList();

        expect(wrapper.text()).toContain('860');
        expect(wrapper.text()).toContain('+8');
    });

    it('says a table has never been loaded rather than showing a blank', async () => {
        const wrapper = await mountList([table({ build: null, loaded_at: null, previous_rows: null, delta: null, rows: 0, is_empty: true })]);

        expect(wrapper.text()).toContain('Jamais chargée');
    });

    it('flags a table left behind on an older build', async () => {
        const wrapper = await mountList([table({ build: '12.1.0.69587', is_stale: true })]);

        expect(wrapper.get('[data-alert="stale"]').text()).toContain('build');
    });

    it('flags an empty table, which no number would make obvious', async () => {
        const wrapper = await mountList([table({ rows: 0, is_empty: true })]);

        expect(wrapper.find('[data-alert="empty"]').exists()).toBe(true);
    });

    it('flags a table that came back thinner than the load before it', async () => {
        const wrapper = await mountList([table({ rows: 700, previous_rows: 1000, delta: -300, has_shrunk: true })]);

        expect(wrapper.get('[data-alert="shrunk"]').text()).toContain('dégarnie');
        expect(wrapper.text()).toContain('-300');
    });

    it('leaves a healthy table without any alert', async () => {
        const wrapper = await mountList();

        expect(wrapper.find('[data-alert]').exists()).toBe(false);
    });

    it('asks for one table to be synced, by its source name', async () => {
        const wrapper = await mountList();

        await wrapper.get('[data-action="sync-table"]').trigger('click');

        expect(wrapper.emitted('sync')).toEqual([['Faction']]);
    });

    it('offers nothing to sync while something is already running', async () => {
        const wrapper = await mountList([table()], { disabled: true });

        expect(wrapper.get('[data-action="sync-table"]').attributes('disabled')).toBeDefined();
    });
});
