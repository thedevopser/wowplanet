import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import HealthServiceList from './HealthServiceList.vue';

const probe = (overrides = {}) => ({ service: 'postgresql', status: 'ok', issue: null, detail: null, ...overrides });

describe('HealthServiceList', () => {
    it('names each service in words', async () => {
        const wrapper = await mountWithPlugins(HealthServiceList, {
            props: { services: [probe(), probe({ service: 'redis:budget' })] },
        });

        expect(wrapper.text()).toContain('PostgreSQL');
        expect(wrapper.text()).toContain('Redis — budget');
    });

    it('says an unreachable service is unreachable, and why', async () => {
        const wrapper = await mountWithPlugins(HealthServiceList, {
            props: { services: [probe({ status: 'unavailable', issue: 'PostgreSQL injoignable.', detail: 'connection refused' })] },
        });

        expect(wrapper.get('[data-service="postgresql"] [data-status]').text()).toBe('Injoignable');
        expect(wrapper.get('[data-service="postgresql"]').text()).toContain('connection refused');
    });

    it('shows no detail for a service that answers', async () => {
        const wrapper = await mountWithPlugins(HealthServiceList, { props: { services: [probe()] } });

        expect(wrapper.find('[data-role="detail"]').exists()).toBe(false);
    });
});
