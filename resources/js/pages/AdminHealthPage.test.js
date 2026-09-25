import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/health', props: {} }),
    router: { reload: (...args) => reload(...args) },
}));

import axios from 'axios';
vi.mock('axios');

import { mountWithPlugins } from '../tests/helpers';
import AdminHealthPage from './AdminHealthPage.vue';
import { expectNoAxeViolations } from '../tests/axe';
import { BUSY_INTERVAL_MS, IDLE_INTERVAL_MS, QUEUE_ENDPOINT } from '../composables/useQueuePolling';

const failedJob = { uuid: 'a1b2', queue: 'imports', job: 'App\\Jobs\\RunImportJob', exception: 'RuntimeException: boom', failed_at: '2026-09-22 09:15:00' };

const healthy = () => ({
    services: [
        { service: 'postgresql', status: 'ok', issue: null, detail: null },
        { service: 'redis:cache', status: 'ok', issue: null, detail: null },
    ],
    quota: { status: 'ok', issue: null, used: 1_200, import_ceiling: 30_000, enforced_limit: 34_000, published_quota: 36_000 },
    queue: { status: 'ok', issue: null, queue: 'imports', pending: 0, delayed: 1, reserved: 0, current: null, running: [], waiting: [], failed: [] },
    volumes: {
        status: 'ok',
        issue: null,
        tables: [{ table: 'wow_mounts', family: 'catalogue', rows: 10, active: 10, without_icon: 0, status: 'ok', issue: null }],
    },
    errors: { status: 'ok', issue: null, entries: [] },
});

const mountPage = (health = healthy()) => mountWithPlugins(AdminHealthPage, { props: { health } });

beforeEach(() => vi.clearAllMocks());

describe('AdminHealthPage', () => {
    it('announces the health section', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('État de santé');
    });

    it('says explicitly that nothing is wrong when nothing is', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="all-clear"]').text()).toContain('Aucune anomalie');
        expect(wrapper.findAll('[data-alert]')).toHaveLength(0);
    });

    it('lists every anomaly in words, whatever section it comes from', async () => {
        const health = healthy();
        health.services[1] = { service: 'redis:cache', status: 'unavailable', issue: 'Redis (cache) injoignable.', detail: 'refused' };
        health.quota = { ...health.quota, status: 'warning', issue: 'Quota proche du plafond réservé aux imports.' };
        health.queue = { ...health.queue, status: 'warning', issue: '1 job échoué en attente de décision.', failed: [failedJob] };
        health.volumes = { status: 'unavailable', issue: 'Volumétries : mesure impossible.', detail: 'no table' };

        const wrapper = await mountPage(health);
        const alerts = wrapper.findAll('[data-alert]').map(alert => alert.text());

        expect(alerts).toEqual([
            'Redis (cache) injoignable.',
            'Quota proche du plafond réservé aux imports.',
            '1 job échoué en attente de décision.',
            'Volumétries : mesure impossible.',
        ]);
        expect(wrapper.find('[data-role="all-clear"]').exists()).toBe(false);
    });

    it('sets the consumed quota against the three ceilings', async () => {
        const quota = (await mountPage()).get('[data-section="quota"]').text().replace(/\s/gu, ' ');

        expect(quota).toContain('1 200');
        expect(quota).toContain('30 000');
        expect(quota).toContain('34 000');
        expect(quota).toContain('36 000');
    });

    it('gives the queue counts', async () => {
        const queue = (await mountPage()).get('[data-section="queue"]');

        expect(queue.get('[data-count="delayed"]').text()).toBe('1');
    });

    it('no longer names a single running import apart from the job lists', async () => {
        const health = healthy();
        health.queue.current = { job_id: 'job-42', started_at: 1_758_530_000 };

        const queue = (await mountPage(health)).get('[data-section="queue"]');

        expect(queue.text()).not.toContain('Import en cours');
        expect(queue.text()).not.toContain('job-42');
    });

    it('says a section it could not measure is unavailable rather than showing it empty', async () => {
        const health = healthy();
        health.volumes = { status: 'unavailable', issue: 'Volumétries : mesure impossible.', detail: 'no table' };

        const volumes = (await mountPage(health)).get('[data-section="volumes"]');

        expect(volumes.text()).toContain('no table');
        expect(volumes.find('table').exists()).toBe(false);
    });

    it('refreshes the diagnosis without leaving the page', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-action="refresh"]').trigger('click');

        expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['health'] }));
        expect(wrapper.get('[data-action="refresh"]').attributes('disabled')).toBeDefined();

        reload.mock.calls[0][0].onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-action="refresh"]').attributes('disabled')).toBeUndefined();
    });

    describe('failed jobs', () => {
        const withFailedJob = () => {
            const health = healthy();
            health.queue = { ...health.queue, status: 'warning', issue: '1 job échoué en attente de décision.', failed: [failedJob] };

            return mountPage(health);
        };

        it('asks for confirmation before retrying, and sends nothing until then', async () => {
            const wrapper = await withFailedJob();

            await wrapper.get('[data-action="retry-job"]').trigger('click');

            expect(wrapper.get('[data-confirm="failed-job"]').text()).toContain('remis dans la queue');
            expect(axios.post).not.toHaveBeenCalled();
        });

        it('retries the job once confirmed, then reloads the diagnosis', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { uuid: 'a1b2' } });
            const wrapper = await withFailedJob();

            await wrapper.get('[data-action="retry-job"]').trigger('click');
            await wrapper.get('[data-action="confirm-failed-job"]').trigger('click');

            expect(axios.post).toHaveBeenCalledWith('/api/admin/failed-jobs/a1b2/retry');
            await vi.waitFor(() => expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['health'] })));
            expect(wrapper.text()).toContain('Job remis dans la queue.');
        });

        it('forgets the job once confirmed', async () => {
            axios.delete = vi.fn().mockResolvedValue({ data: { uuid: 'a1b2' } });
            const wrapper = await withFailedJob();

            await wrapper.get('[data-action="forget-job"]').trigger('click');
            expect(wrapper.get('[data-confirm="failed-job"]').text()).toContain('définitivement');
            await wrapper.get('[data-action="confirm-failed-job"]').trigger('click');

            expect(axios.delete).toHaveBeenCalledWith('/api/admin/failed-jobs/a1b2');
            await vi.waitFor(() => expect(wrapper.text()).toContain('Job supprimé.'));
        });

        it('drops the request when the confirmation is cancelled', async () => {
            const wrapper = await withFailedJob();

            await wrapper.get('[data-action="forget-job"]').trigger('click');
            await wrapper.get('[data-action="cancel-failed-job"]').trigger('click');

            expect(wrapper.find('[data-confirm="failed-job"]').exists()).toBe(false);
        });

        it('shows why the server refused', async () => {
            axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Un import est déjà en cours depuis 3 min 00 s.' } } });
            const wrapper = await withFailedJob();

            await wrapper.get('[data-action="retry-job"]').trigger('click');
            await wrapper.get('[data-action="confirm-failed-job"]').trigger('click');

            await vi.waitFor(() => expect(wrapper.text()).toContain('Un import est déjà en cours depuis 3 min 00 s.'));
        });

        it('falls back to a generic message when the server gave none', async () => {
            axios.delete = vi.fn().mockRejectedValue(new Error('network'));
            const wrapper = await withFailedJob();

            await wrapper.get('[data-action="forget-job"]').trigger('click');
            await wrapper.get('[data-action="confirm-failed-job"]').trigger('click');

            await vi.waitFor(() => expect(wrapper.text()).toContain("L'action sur le job a échoué."));
        });
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage();

        await expectNoAxeViolations(wrapper.element);
    });

    describe('jobs in the queue', () => {
        const NOW = 1_700_000_000;
        const scoreJob = { label: 'Données des autres personnages', account: 'Thrall#1234', since: NOW - 72 };
        const importJob = { label: 'Import du catalogue', account: null, since: NOW - 5 };

        const withJobs = (running, waiting = []) => {
            const health = healthy();
            health.queue = { ...health.queue, running, waiting };

            return health;
        };

        const measured = (running, waiting = [], overrides = {}) => ({ data: { ...healthy().queue, running, waiting, ...overrides } });

        let wrapper;

        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(NOW * 1000);
            axios.get = vi.fn().mockResolvedValue(measured([]));
        });

        afterEach(() => {
            wrapper?.unmount();
            vi.useRealTimers();
        });

        it('lists the running and the waiting jobs with their account and duration', async () => {
            wrapper = await mountPage(withJobs([scoreJob], [importJob]));

            const running = wrapper.get('[data-jobs="running"]');
            expect(running.text()).toContain('En cours');
            expect(running.text()).toContain('Données des autres personnages');
            expect(running.text()).toContain('Thrall#1234');
            expect(running.text()).toContain('depuis 1 min 12 s');
            expect(wrapper.get('[data-jobs="waiting"]').text()).toContain('Import du catalogue');
        });

        it('says no job is running, and shows nothing for an empty wait', async () => {
            wrapper = await mountPage();

            expect(wrapper.get('[data-jobs="running"]').text()).toContain('Aucun job en cours.');
            expect(wrapper.find('[data-jobs="waiting"]').exists()).toBe(false);
        });

        it('lets the duration run between two measures without asking the server', async () => {
            wrapper = await mountPage(withJobs([scoreJob]));

            await vi.advanceTimersByTimeAsync(3000);

            expect(wrapper.get('[data-jobs="running"]').text()).toContain('depuis 1 min 15 s');
            expect(axios.get).not.toHaveBeenCalled();
        });

        it('shows a job appear and disappear without a click', async () => {
            axios.get = vi.fn().mockResolvedValueOnce(measured([scoreJob])).mockResolvedValue(measured([]));
            wrapper = await mountPage();

            await vi.advanceTimersByTimeAsync(IDLE_INTERVAL_MS);
            expect(axios.get).toHaveBeenCalledWith(QUEUE_ENDPOINT);
            expect(wrapper.get('[data-jobs="running"]').text()).toContain('Données des autres personnages');

            await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);
            expect(wrapper.get('[data-jobs="running"]').text()).toContain('Aucun job en cours.');
        });

        it('announces the jobs coming and going, not the running durations', async () => {
            axios.get = vi.fn().mockResolvedValue(measured([scoreJob], [importJob]));
            wrapper = await mountPage(withJobs([scoreJob]));

            const summary = wrapper.get('[data-role="queue-summary"]');
            expect(summary.attributes('aria-live')).toBe('polite');
            expect(summary.text()).toBe('1 job en cours, aucun en attente.');

            await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);

            expect(summary.text()).toBe('1 job en cours, 1 en attente.');
            expect(summary.text()).not.toContain('depuis');
        });

        it('keeps the last measure and says the tracking is interrupted when the network fails', async () => {
            axios.get = vi.fn().mockRejectedValue(new Error('network'));
            wrapper = await mountPage(withJobs([scoreJob]));

            await vi.advanceTimersByTimeAsync(BUSY_INTERVAL_MS);

            expect(wrapper.get('[data-role="queue-interrupted"]').text()).toBe('Mesure de la file interrompue.');
            expect(wrapper.get('[data-jobs="running"]').text()).toContain('Données des autres personnages');
        });

        it('raises the anomaly of a job that failed since the page was opened', async () => {
            axios.get = vi.fn().mockResolvedValue(measured([], [], { status: 'warning', issue: '1 job échoué en attente de décision.', failed: [failedJob] }));
            wrapper = await mountPage();

            await vi.advanceTimersByTimeAsync(IDLE_INTERVAL_MS);

            expect(wrapper.findAll('[data-alert]').map(alert => alert.text())).toEqual(['1 job échoué en attente de décision.']);
            expect(wrapper.find('[data-job="a1b2"]').exists()).toBe(true);
        });

        it('shows no accessibility violation with jobs listed', async () => {
            vi.useRealTimers();
            wrapper = await mountPage(withJobs([scoreJob], [importJob]));

            await expectNoAxeViolations(wrapper.element);
        });
    });
});
