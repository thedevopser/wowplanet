import { describe, it, expect, vi, beforeEach } from 'vitest';

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

const failedJob = { uuid: 'a1b2', queue: 'imports', job: 'App\\Jobs\\RunImportJob', exception: 'RuntimeException: boom', failed_at: '2026-09-22 09:15:00' };

const healthy = () => ({
    services: [
        { service: 'postgresql', status: 'ok', issue: null, detail: null },
        { service: 'redis:cache', status: 'ok', issue: null, detail: null },
    ],
    quota: { status: 'ok', issue: null, used: 1_200, import_ceiling: 30_000, enforced_limit: 34_000, published_quota: 36_000 },
    queue: { status: 'ok', issue: null, queue: 'imports', pending: 0, delayed: 1, reserved: 0, current: null, failed: [] },
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

    it('gives the queue counts and the running import', async () => {
        const health = healthy();
        health.queue.current = { job_id: 'job-42', started_at: 1_758_530_000 };

        const queue = (await mountPage(health)).get('[data-section="queue"]');

        expect(queue.get('[data-count="delayed"]').text()).toBe('1');
        expect(queue.text()).toContain('job-42');
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
});
