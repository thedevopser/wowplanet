import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import QueueJobList from './QueueJobList.vue';

const NOW = 1_700_000_000;

const job = (overrides = {}) => ({ label: 'Données des autres personnages', account: 'Thrall#1234', since: NOW - 72, ...overrides });

const mountList = props => mountWithPlugins(QueueJobList, { props: { now: NOW, ...props } });

describe('QueueJobList', () => {
    it('names each job with its account and how long it has been there', async () => {
        const wrapper = await mountList({ jobs: [job()] });

        const entry = wrapper.get('[data-queued-job]');
        expect(entry.text()).toContain('Données des autres personnages');
        expect(entry.get('[data-role="account"]').text()).toBe('Thrall#1234');
        expect(entry.get('[data-role="since"]').text()).toBe('depuis 1 min 12 s');
        expect(entry.get('[data-role="since"]').classes()).toContain('tabular-nums');
    });

    it('shows no account for a job that serves none', async () => {
        const wrapper = await mountList({ jobs: [job({ label: 'Import du catalogue', account: null })] });

        expect(wrapper.find('[data-role="account"]').exists()).toBe(false);
    });

    it('never shows a negative duration when the browser clock lags behind the server', async () => {
        const wrapper = await mountList({ jobs: [job({ since: NOW + 3 })] });

        expect(wrapper.get('[data-role="since"]').text()).toBe('depuis 0 s');
    });

    it('says the list is empty when told how to', async () => {
        const wrapper = await mountList({ jobs: [], emptyText: 'Aucun job en cours.' });

        expect(wrapper.get('[data-role="no-queued-job"]').text()).toBe('Aucun job en cours.');
        expect(wrapper.find('ul').exists()).toBe(false);
    });

    it('renders nothing for an empty list without a message', async () => {
        const wrapper = await mountList({ jobs: [] });

        expect(wrapper.text()).toBe('');
    });
});
