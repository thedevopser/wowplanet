import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import ApplicationErrorList from './ApplicationErrorList.vue';

const entry = (overrides = {}) => ({
    id: 1,
    level: 'ERROR',
    message: 'Failed to fetch talents',
    exception_class: null,
    location: null,
    occurred_at: '2026-09-22T09:15:00+00:00',
    ...overrides,
});

describe('ApplicationErrorList', () => {
    it('says so when nothing went wrong', async () => {
        const wrapper = await mountWithPlugins(ApplicationErrorList, { props: { entries: [] } });

        expect(wrapper.get('[data-role="no-error"]').text()).toContain('Aucune erreur enregistrée');
    });

    it('gives each error with its timestamp and level', async () => {
        const wrapper = await mountWithPlugins(ApplicationErrorList, { props: { entries: [entry()] } });
        const row = wrapper.get('[data-error="1"]');

        expect(row.text()).toContain('Failed to fetch talents');
        expect(row.text()).toContain('ERROR');
        expect(row.get('time').attributes('datetime')).toBe('2026-09-22T09:15:00+00:00');
    });

    it('shows where an exception was thrown when the entry carries one', async () => {
        const wrapper = await mountWithPlugins(ApplicationErrorList, {
            props: { entries: [entry({ exception_class: 'RuntimeException', location: 'app/Jobs/RunImportJob.php:42' })] },
        });

        expect(wrapper.get('[data-role="origin"]').text()).toBe('RuntimeException — app/Jobs/RunImportJob.php:42');
    });

    it('names the exception alone when its location is unknown', async () => {
        const wrapper = await mountWithPlugins(ApplicationErrorList, {
            props: { entries: [entry({ exception_class: 'RuntimeException' })] },
        });

        expect(wrapper.get('[data-role="origin"]').text()).toBe('RuntimeException');
    });

    // A message can hold a path or a class name without a single space: it must wrap on a phone.
    it('lets a message without spaces wrap instead of widening the page', async () => {
        const wrapper = await mountWithPlugins(ApplicationErrorList, { props: { entries: [entry()] } });

        expect(wrapper.get('[data-role="message"]').classes()).toEqual(expect.arrayContaining(['min-w-0', 'wrap-anywhere']));
    });
});
