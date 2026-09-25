import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/history/job-1', props: {} }),
}));

import { mountWithPlugins } from '../tests/helpers';
import AdminHistoryEntryPage from './AdminHistoryEntryPage.vue';

const entry = (overrides = {}) => ({
    job_id: 'job-1',
    trigger: '12345',
    mode: 'forced',
    status: 'cancelled',
    started_at: '2026-09-22T10:00:00+00:00',
    finished_at: '2026-09-22T10:02:00+00:00',
    budget_used: 1_234,
    steps: [{
        stage: 'mounts', label: 'Montures', status: 'completed', created: 1, updated: 0, deleted: 0,
        api_calls: 5, duration_ms: 1_000, rows_after: 10, error: null,
        previous_rows: 10, previous_job_id: 'job-0', delta: 0, shrunk: false,
    }],
    journal: ['[10:00:00] Import démarré — 1 étape : Montures.'],
    ...overrides,
});

const mountPage = (overrides = {}) => mountWithPlugins(AdminHistoryEntryPage, { props: { entry: entry(overrides) } });

describe('AdminHistoryEntryPage', () => {
    it('says who launched the import, how, and how it ended', async () => {
        const wrapper = await mountPage();
        const header = wrapper.get('[data-role="summary"]').text().replace(/\s/gu, ' ');

        expect(header).toContain('12345');
        expect(header).toContain('Forcé');
        expect(header).toContain('Annulé');
        expect(header).toContain('2 min 00 s');
        expect(header).toContain('1 234');
    });

    it('carries the report of each stage', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-stage="mounts"]').exists()).toBe(true);
    });

    it('shows the journal while it lives', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-role="journal"]').text()).toContain('Import démarré');
    });

    it('says the journal has expired rather than showing it empty', async () => {
        const wrapper = await mountPage({ journal: null });

        expect(wrapper.find('[data-role="journal"]').exists()).toBe(false);
        expect(wrapper.get('[data-role="journal-expired"]').text()).toContain('expiré');
    });

    it('says an unfinished import has not ended', async () => {
        const wrapper = await mountPage({ status: 'abandoned', finished_at: null });

        expect(wrapper.get('[data-role="summary"]').text()).toContain('non clôturé');
    });

    it('leads back to the history', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-action="back"]').attributes('href')).toBe('/admin/history');
    });
});
