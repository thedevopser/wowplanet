import { describe, it, expect, vi, beforeEach } from 'vitest';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { reload: (...args) => reload(...args) },
    usePage: () => ({ props: {} }),
}));

import axios from 'axios';
vi.mock('axios');

import { mountWithPlugins } from '../../tests/helpers';
import TaxonomySnapshotPanel from './TaxonomySnapshotPanel.vue';

const snapshot = (overrides = {}) => ({
    path: '/app/database/data/collection_taxonomy.csv',
    entries: 6_106,
    in_step: true,
    missing_in_base: 0,
    missing_in_file: 0,
    differing: 0,
    ...overrides,
});

const mountPanel = (overrides = {}) => mountWithPlugins(TaxonomySnapshotPanel, { props: { snapshot: snapshot(overrides) } });

beforeEach(() => vi.clearAllMocks());

describe('TaxonomySnapshotPanel', () => {
    it('offers the snapshot rendered from the base for download, to be committed', async () => {
        const wrapper = await mountPanel();
        const link = wrapper.get('[data-action="download-snapshot"]');

        expect(link.attributes('href')).toBe('/api/admin/taxonomy/snapshot');
        expect(link.attributes('download')).toBeDefined();
        expect(wrapper.text()).toContain('database/data/collection_taxonomy.csv');
    });

    it('says explicitly that base and snapshot are in step', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.get('[data-role="in-step"]').text()).toContain('en phase');
        expect(wrapper.find('[data-alert]').exists()).toBe(false);
    });

    it('never advises a terminal command', async () => {
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 3, missing_in_file: 2, differing: 1 });

        expect(wrapper.text()).not.toContain('php artisan');
    });

    it('offers to reload the snapshot when the base lacks some of its entries', async () => {
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 6_106 });

        expect(wrapper.get('[data-alert="missing-in-base"]').text().replace(/\s/gu, ' ')).toContain('6 106 entrées de l\'instantané manquent en base');
        expect(wrapper.find('[data-action="load-snapshot"]').exists()).toBe(true);
    });

    it('asks to download and commit when the base holds what the snapshot does not', async () => {
        const wrapper = await mountPanel({ in_step: false, missing_in_file: 12, differing: 3 });

        const alert = wrapper.get('[data-alert="missing-in-file"]').text();
        expect(alert).toContain('12 entrées arbitrées en base');
        expect(alert).toContain('3 entrées diffèrent');
        expect(wrapper.find('[data-action="load-snapshot"]').exists()).toBe(false);
    });

    it('names a single entry in the singular', async () => {
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 1, missing_in_file: 1, differing: 1 });

        expect(wrapper.get('[data-alert="missing-in-base"]').text()).toContain('1 entrée de l\'instantané manque en base');
        expect(wrapper.get('[data-alert="missing-in-file"]').text()).toContain('1 entrée arbitrée en base');
        expect(wrapper.get('[data-alert="missing-in-file"]').text()).toContain('1 entrée diffère');
    });

    it('reloads the snapshot only once confirmed, then refreshes the screen', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { inserted: 6_106 } });
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 6_106 });

        await wrapper.get('[data-action="load-snapshot"]').trigger('click');
        expect(axios.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-confirm="load-snapshot"]').text()).toContain('rien de ce qui est déjà en base');

        await wrapper.get('[data-action="confirm-load-snapshot"]').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/taxonomy/load');
        await vi.waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['entries', 'counts', 'matched', 'vocabulary', 'snapshot'] }));
        expect(wrapper.text().replace(/\s/gu, ' ')).toContain('6 106 entrées chargées');
    });

    it('drops the reload when the confirmation is cancelled', async () => {
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 2 });

        await wrapper.get('[data-action="load-snapshot"]').trigger('click');
        await wrapper.get('[data-action="cancel-load-snapshot"]').trigger('click');

        expect(wrapper.find('[data-confirm="load-snapshot"]').exists()).toBe(false);
    });

    it('shows why a reload failed', async () => {
        axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Base injoignable' } } });
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 2 });

        await wrapper.get('[data-action="load-snapshot"]').trigger('click');
        await wrapper.get('[data-action="confirm-load-snapshot"]').trigger('click');

        await vi.waitFor(() => expect(wrapper.text()).toContain('Base injoignable'));
    });

    it('falls back to a generic message when the server gave none', async () => {
        axios.post = vi.fn().mockRejectedValue(new Error('network'));
        const wrapper = await mountPanel({ in_step: false, missing_in_base: 2 });

        await wrapper.get('[data-action="load-snapshot"]').trigger('click');
        await wrapper.get('[data-action="confirm-load-snapshot"]').trigger('click');

        await vi.waitFor(() => expect(wrapper.text()).toContain('Le rechargement de l\'instantané a échoué.'));
    });
});
