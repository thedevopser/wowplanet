import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: {} }),
}));

import { mountWithPlugins } from '../../tests/helpers';
import BuildStatusEntryList from './BuildStatusEntryList.vue';

const entry = (overrides = {}) => ({
    stage: 'mounts',
    label: 'Montures',
    upstream: 'blizzard',
    build: '12.1.0_68914',
    upstream_build: '12.1.0_68914',
    imported_at: '2026-09-19T19:38:50+00:00',
    state: 'current',
    note: null,
    ...overrides,
});

const mountList = (entries, props = {}) => mountWithPlugins(BuildStatusEntryList, {
    props: { entries, ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('BuildStatusEntryList', () => {
    it('shows one row per entry, in the order it is given', async () => {
        const wrapper = await mountList([
            entry({ stage: 'reference', label: 'Socle de référence', upstream: 'wago' }),
            entry(),
        ]);

        const rows = wrapper.findAll('[data-stage]');

        expect(rows).toHaveLength(2);
        expect(rows[0].attributes('data-stage')).toBe('reference');
    });

    it('flags an entity left behind and carries both builds on its row', async () => {
        const wrapper = await mountList([entry({ state: 'stale', build: '12.1.0_68000' })]);

        const row = wrapper.get('[data-stage="mounts"]');

        expect(row.get('[data-alert="stale"]').exists()).toBe(true);
        expect(row.text()).toContain('12.1.0_68000');
        expect(row.text()).toContain('12.1.0_68914');
    });

    it('says an entity was never imported rather than showing it up to date', async () => {
        const wrapper = await mountList([entry({ state: 'never', build: null, imported_at: null })]);

        expect(wrapper.get('[data-stage="mounts"] [data-alert="never"]').text()).toContain('Jamais importée');
    });

    it('marks an entity whose upstream could not be read as not comparable', async () => {
        const wrapper = await mountList([entry({ state: 'unknown', upstream_build: null })]);

        expect(wrapper.get('[data-stage="mounts"] [data-alert="unknown"]').exists()).toBe(true);
    });

    it('leaves an up to date entity without an alert', async () => {
        const wrapper = await mountList([entry()]);

        expect(wrapper.find('[data-stage="mounts"] [data-alert]').exists()).toBe(false);
    });

    it('emits the one stage a per-entity button asks to update', async () => {
        const wrapper = await mountList([entry({ state: 'stale' })]);

        await wrapper.get('[data-action="update-stage"]').trigger('click');

        expect(wrapper.emitted('update')[0]).toEqual(['mounts']);
    });

    it('offers no update on an entity that is already current', async () => {
        const wrapper = await mountList([entry()]);

        expect(wrapper.get('[data-action="update-stage"]').attributes('disabled')).toBeDefined();
    });

    it('offers no update on an entity whose upstream is unreadable, having nothing to go on', async () => {
        const wrapper = await mountList([entry({ state: 'unknown' })]);

        expect(wrapper.get('[data-action="update-stage"]').attributes('disabled')).toBeDefined();
    });

    it('launches nothing while an import is already running', async () => {
        const wrapper = await mountList([entry({ state: 'stale' })], { disabled: true });

        expect(wrapper.get('[data-action="update-stage"]').attributes('disabled')).toBeDefined();
    });

    it('says what the socle build alone does not, when only some of its tables are behind', async () => {
        const wrapper = await mountList([entry({
            stage: 'reference', label: 'Socle de référence', upstream: 'wago', state: 'stale',
            build: '12.1.0.69875', upstream_build: '12.1.0.69875', note: '1 table sur 8 en retard',
        })]);

        expect(wrapper.get('[data-stage="reference"]').text()).toContain('1 table sur 8 en retard');
    });

    it('leads to the socle page for the table by table detail', async () => {
        const wrapper = await mountList([entry({ stage: 'reference', label: 'Socle de référence', upstream: 'wago' })]);

        expect(wrapper.get('[data-stage="reference"] a').attributes('href')).toBe('/admin/reference');
    });

    it('gives the link to the detail of the socle a 44 px target', async () => {
        const wrapper = await mountList([entry({ stage: 'reference', label: 'Socle', upstream: 'wago' })]);

        expect(wrapper.get('a[href="/admin/reference"]').classes()).toContain('min-h-11');
    });
});
