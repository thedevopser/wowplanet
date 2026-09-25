import { describe, it, expect, vi, beforeEach } from 'vitest';

const visit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/history', props: {} }),
    router: { visit: (...args) => visit(...args) },
}));

import { mountWithPlugins } from '../tests/helpers';
import AdminHistoryPage from './AdminHistoryPage.vue';

const entry = (jobId) => ({
    job_id: jobId, trigger: 'console', mode: 'incremental', status: 'completed',
    started_at: '2026-09-22T10:00:00+00:00', finished_at: '2026-09-22T10:01:00+00:00', budget_used: 0,
    stages: [{ stage: 'mounts', label: 'Montures' }], shrunk: [],
});

const mountPage = (history = { entries: [entry('job-1'), entry('job-2')], page: 1, pages: 1 }) => mountWithPlugins(AdminHistoryPage, { props: { history } });

beforeEach(() => vi.clearAllMocks());

describe('AdminHistoryPage', () => {
    it('announces the history and how long it is kept', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Historique des imports');
        expect(wrapper.text()).toContain('douze mois');
    });

    it('compares nothing until two imports are selected', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-action="compare"]').attributes('disabled')).toBeDefined();

        await wrapper.get('[data-entry="job-1"] input[type="checkbox"]').setValue(true);

        expect(wrapper.get('[data-action="compare"]').attributes('disabled')).toBeDefined();
    });

    it('opens the comparison of the two selected imports', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-entry="job-1"] input[type="checkbox"]').setValue(true);
        await wrapper.get('[data-entry="job-2"] input[type="checkbox"]').setValue(true);
        await wrapper.get('[data-action="compare"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/admin/history/compare?first=job-1&second=job-2');
    });

    it('offers no page navigation when everything fits on one page', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-role="pagination"]').exists()).toBe(false);
    });

    it('links to the neighbouring pages', async () => {
        const wrapper = await mountPage({ entries: [entry('job-1')], page: 2, pages: 3 });

        expect(wrapper.get('[data-action="previous-page"]').attributes('href')).toBe('/admin/history?page=1');
        expect(wrapper.get('[data-action="next-page"]').attributes('href')).toBe('/admin/history?page=3');
        expect(wrapper.get('[data-role="pagination"]').text()).toContain('2 / 3');
    });

    it('offers no previous page on the first one, nor a next page on the last one', async () => {
        const wrapper = await mountPage({ entries: [entry('job-1')], page: 1, pages: 2 });
        const last = await mountPage({ entries: [entry('job-1')], page: 2, pages: 2 });

        expect(wrapper.find('[data-action="previous-page"]').exists()).toBe(false);
        expect(last.find('[data-action="next-page"]').exists()).toBe(false);
    });
});
