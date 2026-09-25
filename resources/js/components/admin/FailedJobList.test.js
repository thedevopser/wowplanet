import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import FailedJobList from './FailedJobList.vue';

const job = (overrides = {}) => ({
    uuid: 'a1b2',
    queue: 'imports',
    job: 'App\\Jobs\\RunImportJob',
    exception: 'RuntimeException: the quota is exhausted in /app/Jobs/RunImportJob.php:42',
    failed_at: '2026-09-22 09:15:00',
    ...overrides,
});

const mountList = (jobs = [job()], props = {}) => mountWithPlugins(FailedJobList, { props: { jobs, disabled: false, ...props } });

describe('FailedJobList', () => {
    it('says so when no job failed', async () => {
        const wrapper = await mountList([]);

        expect(wrapper.get('[data-role="no-failed-job"]').text()).toContain('Aucun job échoué');
    });

    it('gives each failed job with its class, its message and when it failed', async () => {
        const wrapper = await mountList();
        const row = wrapper.get('[data-job="a1b2"]');

        expect(row.text()).toContain('RunImportJob');
        expect(row.text()).toContain('the quota is exhausted');
        expect(row.text()).toContain('2026-09-22 09:15:00');
    });

    it.each([
        ['retry-job', 'retry'],
        ['forget-job', 'forget'],
    ])('asks for the %s action on the job it names', async (action, event) => {
        const wrapper = await mountList();

        await wrapper.get(`[data-action="${action}"]`).trigger('click');

        expect(wrapper.emitted(event)).toEqual([['a1b2']]);
    });

    it('locks its buttons while an action is under way', async () => {
        const wrapper = await mountList([job()], { disabled: true });

        expect(wrapper.get('[data-action="retry-job"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-action="forget-job"]').attributes('disabled')).toBeDefined();
    });
});
