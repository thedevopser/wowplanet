import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/reference', props: {} }),
    router: { reload: (...args) => reload(...args) },
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminReferencePage from './AdminReferencePage.vue';

vi.mock('axios');

const tables = [
    {
        source: 'Faction', table: 'wow_ref_faction', rows: 868, build: '12.1.0.69875',
        loaded_at: '2026-09-19T19:38:50+00:00', previous_rows: 860, delta: 8,
        is_empty: false, has_shrunk: false, is_stale: false,
    },
    {
        source: 'AreaTable', table: 'wow_ref_area_table', rows: 10002, build: '12.1.0.69587',
        loaded_at: '2026-09-12T16:17:46+00:00', previous_rows: null, delta: null,
        is_empty: false, has_shrunk: false, is_stale: true,
    },
];

const progress = (overrides = {}) => ({
    data: {
        status: 'running',
        stage: null,
        stage_label: null,
        percent: 0,
        elapsed_seconds: 2,
        eta_seconds: null,
        budget: { used: 0, ceiling: 30000 },
        waiting: null,
        steps: [],
        log: { cursor: 1, lines: ['[10:00:00] Socle de référence — build 12.1.0.69875'] },
        ...overrides,
    },
});

const storeFile = (overrides = {}) => ({
    filename: 'faction-12.1.0.69875.csv',
    state: 'live',
    bytes: 157_683,
    source_table: 'Faction',
    build: '12.1.0.69875',
    loaded_at: 1_758_358_502,
    ...overrides,
});

const store = (overrides = {}) => ({
    files: [
        storeFile(),
        storeFile({ filename: 'faction-12.1.0.69587.csv', state: 'obsolete', bytes: 90, build: '12.1.0.69587' }),
    ],
    missing: [],
    totals: { files: 2, bytes: 157_773, sweepable_files: 1, sweepable_bytes: 90 },
    ...overrides,
});

// Le suivi interroge l'import courant au montage : rien ne tourne pour les tests qui ne
// suivent rien.
const mountPage = async (props = {}) => {
    axios.get = vi.fn().mockResolvedValue({ data: { jobId: null } });

    return mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875', ...props } });
};

const followingAJob = () => {
    axios.get = vi.fn()
        .mockResolvedValueOnce({ data: { jobId: null } })
        .mockResolvedValue(progress());
};

const buttonLabelled = (wrapper, label) => wrapper.findAll('button').find(b => b.text() === label);

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
});

afterEach(() => vi.useRealTimers());

describe('AdminReferencePage', () => {
    it('lists the tables of the socle', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Faction');
        expect(wrapper.text()).toContain('AreaTable');
    });

    it('names the build wago serves, so the comparison is readable', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('12.1.0.69875');
    });

    it('says the live build could not be read rather than staying silent', async () => {
        const wrapper = await mountPage({ liveBuild: null });

        expect(wrapper.text()).toContain('build courant');
    });

    it('syncs the whole socle on demand', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        followingAJob();

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await buttonLabelled(wrapper, 'Tout synchroniser').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/reference/sync', { scope: 'all' });

        wrapper.unmount();
    });

    it('syncs a single table, sending its source name', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        followingAJob();

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await wrapper.findAll('[data-action="sync-table"]')[0].trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/reference/sync', { scope: 'table', table: 'Faction' });

        wrapper.unmount();
    });

    it('follows the sync in the journal as it runs', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        followingAJob();

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await buttonLabelled(wrapper, 'Tout synchroniser').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain('Socle de référence — build 12.1.0.69875');

        wrapper.unmount();
    });

    it('refuses to offer a sync while one is already running', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        followingAJob();

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await buttonLabelled(wrapper, 'Tout synchroniser').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(buttonLabelled(wrapper, 'Tout synchroniser').attributes('disabled')).toBeDefined();

        wrapper.unmount();
    });

    it('says why the server refused a sync instead of staying silent', async () => {
        axios.post = vi.fn().mockRejectedValue({
            response: { data: { message: 'Un import est déjà en cours depuis 2 min 00 s.' } },
        });
        axios.get = vi.fn().mockResolvedValue({ data: { jobId: null } });

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await buttonLabelled(wrapper, 'Tout synchroniser').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain('Un import est déjà en cours depuis 2 min 00 s.');
    });

    it('picks up a run already in flight when the page opens', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: 'job-ailleurs' } })
            .mockResolvedValue(progress());

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.get).toHaveBeenCalledWith('/api/admin/import/job-ailleurs?cursor=0');

        wrapper.unmount();
    });

    it('says what the store holds and what a sweep would free', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('154 ko');
        expect(wrapper.text()).toContain('90 o');
    });

    it('lists the files of the store', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('faction-12.1.0.69587.csv');
    });

    it('offers nothing to sweep when nothing is obsolete', async () => {
        const wrapper = await mountPage({
            store: store({ files: [storeFile()], totals: { files: 1, bytes: 157_683, sweepable_files: 0, sweepable_bytes: 0 } }),
        });

        expect(buttonLabelled(wrapper, 'Nettoyer les obsolètes').attributes('disabled')).toBeDefined();
    });

    it('asks to confirm a sweep, chiffre en main, before sending anything', async () => {
        axios.post = vi.fn();

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Nettoyer les obsolètes').trigger('click');

        expect(wrapper.get('[data-confirm="purge"]').text()).toContain('90 o');
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('sweeps the obsolete once confirmed', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { files: 1, bytes: 90 } });

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Nettoyer les obsolètes').trigger('click');
        await buttonLabelled(wrapper, 'Supprimer quand même').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/reference/purge', { scope: 'obsolete' });
    });

    it('drops the confirmation when it is called off, and sends nothing', async () => {
        axios.post = vi.fn();

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Nettoyer les obsolètes').trigger('click');
        await buttonLabelled(wrapper, 'Annuler').trigger('click');

        expect(wrapper.find('[data-confirm="purge"]').exists()).toBe(false);
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('sends a single file by name when one is removed on its own', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { files: 1, bytes: 157_683 } });

        const wrapper = await mountPage();
        await wrapper.findAll('[data-action="delete-file"]')[0].trigger('click');
        await buttonLabelled(wrapper, 'Supprimer quand même').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/reference/purge', {
            scope: 'selection',
            files: ['faction-12.1.0.69875.csv'],
        });
    });

    it('warns that a file in service is about to go, since nothing else would', async () => {
        const wrapper = await mountPage();
        await wrapper.findAll('[data-action="delete-file"]')[0].trigger('click');

        expect(wrapper.get('[data-confirm="purge"]').text()).toContain('en service');
    });

    it('sweeps a hand-picked selection', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { files: 1, bytes: 90 } });

        const wrapper = await mountPage();
        await wrapper.get('[data-action="select-file"]').setValue(true);
        await buttonLabelled(wrapper, 'Supprimer la sélection').trigger('click');
        await buttonLabelled(wrapper, 'Supprimer quand même').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/reference/purge', {
            scope: 'selection',
            files: ['faction-12.1.0.69587.csv'],
        });
    });

    it('offers nothing to remove while no file is selected', async () => {
        const wrapper = await mountPage();

        expect(buttonLabelled(wrapper, 'Supprimer la sélection').attributes('disabled')).toBeDefined();
    });

    it('reads the store again once a purge is through, so the figures stop lying', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { files: 1, bytes: 90 } });

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Nettoyer les obsolètes').trigger('click');
        await buttonLabelled(wrapper, 'Supprimer quand même').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(reload).toHaveBeenCalledWith({ only: ['store', 'tables'] });
    });

    it('says how much a purge actually freed', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { files: 1, bytes: 90 } });

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Nettoyer les obsolètes').trigger('click');
        await buttonLabelled(wrapper, 'Supprimer quand même').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain('90 o libérés');
    });

    it('says why the server refused a purge instead of staying silent', async () => {
        axios.post = vi.fn().mockRejectedValue({
            response: { data: { message: 'Un import est déjà en cours depuis 2 min 00 s.' } },
        });

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Nettoyer les obsolètes').trigger('click');
        await buttonLabelled(wrapper, 'Supprimer quand même').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain('Un import est déjà en cours depuis 2 min 00 s.');
    });

    it('offers no purge while a sync is running, the server would refuse it anyway', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
        followingAJob();

        const wrapper = await mountWithPlugins(AdminReferencePage, { props: { tables, store: store(), liveBuild: '12.1.0.69875' } });
        await buttonLabelled(wrapper, 'Tout synchroniser').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(buttonLabelled(wrapper, 'Nettoyer les obsolètes').attributes('disabled')).toBeDefined();

        wrapper.unmount();
    });
});
