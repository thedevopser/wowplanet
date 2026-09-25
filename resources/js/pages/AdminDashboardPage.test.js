import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin', props: {} }),
    router: { reload: (...args) => reload(...args) },
    Deferred: { name: 'Deferred', props: ['data'], template: '<div><slot /></div>' },
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminDashboardPage from './AdminDashboardPage.vue';

vi.mock('axios');

const counts = (overrides = {}) => ({
    mount: { pending: 117, catalogue: 1663 },
    pet: { pending: 680, catalogue: 2177 },
    decor: { pending: 0, catalogue: 2131 },
    ...overrides,
});

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

const buildStatus = (overrides = {}) => ({
    upstreams: {
        blizzard: { label: 'API Blizzard', build: '12.1.0_68914', checked_at: '2026-09-21T09:12:04+00:00', reachable: true },
        wago: { label: 'wago.tools', build: '12.1.0.69875', checked_at: '2026-09-21T09:12:04+00:00', reachable: true },
    },
    entries: [entry({ stage: 'reference', label: 'Socle de référence', upstream: 'wago', build: '12.1.0.69875', upstream_build: '12.1.0.69875' }), entry()],
    behind: [],
    is_up_to_date: true,
    is_conclusive: true,
    ...overrides,
});

const progress = () => ({
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
        log: { cursor: 1, lines: ['[10:00:00] Import démarré'] },
    },
});

// Le suivi interroge l'import courant au montage — rien ne tourne alors — puis l'import
// qu'on vient de lancer, qui lui rapporte son avancement.
const mountPage = async (props = {}, options = {}) => {
    axios.get = vi.fn()
        .mockResolvedValueOnce({ data: { jobId: null } })
        .mockResolvedValue(progress());

    return mountWithPlugins(AdminDashboardPage, {
        props: { pendingTaxonomy: counts(), buildStatus: buildStatus(), ...props },
        ...options,
    });
};

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
});

afterEach(() => vi.useRealTimers());

describe('AdminDashboardPage', () => {
    it('announces the panel', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Administration');
    });

    it('totals what the collections have left to arbitrate, which is the point of showing it here', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="pending-taxonomy"]').text()).toContain('797');
    });

    it('breaks the total down by collection', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="pending-taxonomy"]').text()).toContain('680');
    });

    it('leads to the screen where the arbitration happens', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="pending-taxonomy"] a').attributes('href')).toBe('/admin/taxonomy');
    });

    it('says the collections are fully ranked rather than showing a zero', async () => {
        const wrapper = await mountPage({
            pendingTaxonomy: counts({ mount: { pending: 0, catalogue: 1663 }, pet: { pending: 0, catalogue: 2177 } }),
        });

        expect(wrapper.get('[data-role="pending-taxonomy"]').text()).toContain('Tout est rangé');
    });

    it('opens on the state of the builds', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="build-status"]').exists()).toBe(true);
    });

    it('shows a placeholder while the check is still out', async () => {
        const wrapper = await mountPage({}, {
            global: { stubs: { Deferred: { template: '<div><slot name="fallback" /></div>' } } },
        });

        expect(wrapper.get('[data-role="build-status-loading"]').exists()).toBe(true);
    });

    it('updates the entities the server listed, in an incremental import', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-9' } });
        const wrapper = await mountPage({
            buildStatus: buildStatus({ behind: ['reference', 'mounts'], is_up_to_date: false }),
        });

        await wrapper.get('[data-action="update-all"]').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import', {
            scope: 'selection',
            stages: ['reference', 'mounts'],
            mode: 'incremental',
        });
    });

    it('updates a single entity without touching the others', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-9' } });
        const wrapper = await mountPage({
            buildStatus: buildStatus({
                entries: [entry({ state: 'stale', build: '12.1.0_68000' })],
                behind: ['mounts'],
                is_up_to_date: false,
            }),
        });

        await wrapper.get('[data-action="update-stage"]').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import', {
            scope: 'selection',
            stages: ['mounts'],
            mode: 'incremental',
        });
    });

    it('says why an import could not start rather than staying silent', async () => {
        axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Un import est déjà en cours' } } });
        const wrapper = await mountPage({
            buildStatus: buildStatus({ behind: ['mounts'], is_up_to_date: false }),
        });

        await wrapper.get('[data-action="update-all"]').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Un import est déjà en cours'));
    });

    it('reloads only the banner after a forced check', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { checked: true } });
        const wrapper = await mountPage();

        await wrapper.get('[data-action="check"]').trigger('click');
        await vi.waitFor(() => expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['buildStatus'] })));

        expect(axios.post).toHaveBeenCalledWith('/api/admin/build-check');
    });

    it('shows the check under way until the banner comes back', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { checked: true } });
        const wrapper = await mountPage();

        await wrapper.get('[data-action="check"]').trigger('click');
        await vi.waitFor(() => expect(wrapper.get('[data-action="check"]').text()).toContain('Vérification'));

        // Le rechargement de la prop est ce qui clôt l'attente : tant qu'il n'est pas
        // revenu, le bouton ne doit pas se rallumer comme si c'était fait.
        reload.mock.calls[0][0].onFinish();
        await vi.waitFor(() => expect(wrapper.get('[data-action="check"]').text()).toContain('Revérifier'));
    });

    it('lifts the check when the server refuses it, rather than staying stuck', async () => {
        axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Amont injoignable' } } });
        const wrapper = await mountPage();

        await wrapper.get('[data-action="check"]').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Amont injoignable'));

        expect(wrapper.get('[data-action="check"]').text()).toContain('Revérifier');
    });
});
