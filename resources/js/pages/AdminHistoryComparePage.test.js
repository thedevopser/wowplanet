import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/history/compare', props: {} }),
}));

import { mountWithPlugins } from '../tests/helpers';
import AdminHistoryComparePage from './AdminHistoryComparePage.vue';

const header = (jobId, startedAt, overrides = {}) => ({
    job_id: jobId, trigger: 'console', mode: 'incremental', status: 'completed',
    started_at: startedAt, finished_at: startedAt, budget_used: 0, ...overrides,
});

const comparison = () => ({
    older: header('job-1', '2026-09-01T10:00:00+00:00'),
    newer: header('job-2', '2026-09-10T10:00:00+00:00', { status: 'cancelled' }),
    stages: [],
});

describe('AdminHistoryComparePage', () => {
    it('names the two imports, the older first, each linked to its report', async () => {
        const wrapper = await mountWithPlugins(AdminHistoryComparePage, { props: { comparison: comparison() } });

        expect(wrapper.get('[data-side="older"] a').attributes('href')).toBe('/admin/history/job-1');
        expect(wrapper.get('[data-side="newer"] a').attributes('href')).toBe('/admin/history/job-2');
        expect(wrapper.get('[data-side="newer"]').text()).toContain('Annulé');
    });

    it('lays the shared entities side by side', async () => {
        const wrapper = await mountWithPlugins(AdminHistoryComparePage, { props: { comparison: comparison() } });

        expect(wrapper.find('[data-role="nothing-shared"]').exists()).toBe(true);
    });
});
