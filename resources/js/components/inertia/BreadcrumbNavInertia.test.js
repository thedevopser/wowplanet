import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BreadcrumbNavInertia from './BreadcrumbNavInertia.vue';

const mountCrumbs = crumbs => mount(BreadcrumbNavInertia, { props: { crumbs } });

describe('BreadcrumbNavInertia', () => {
    it('is a named navigation landmark', () => {
        const wrapper = mountCrumbs([]);

        expect(wrapper.find('nav').attributes('aria-label')).toBe('Fil d’Ariane');
    });

    it('always starts from the home page', () => {
        const wrapper = mountCrumbs([]);

        expect(wrapper.find('a').attributes('href')).toBe('/');
        expect(wrapper.find('a').text()).toBe('WowPlanet');
    });

    it('lists every step, home included', () => {
        const wrapper = mountCrumbs([{ label: 'FAQ', to: '/faq' }]);

        expect(wrapper.findAll('ol > li')).toHaveLength(2);
    });

    it('links every crumb but the last one', () => {
        const wrapper = mountCrumbs([
            { label: 'Base de données', to: '/base-de-donnees' },
            { label: 'Montures', to: '/base-de-donnees/montures' },
        ]);

        const hrefs = wrapper.findAll('a').map(a => a.attributes('href'));

        expect(hrefs).toEqual(['/', '/base-de-donnees']);
    });

    it('marks the last crumb as the current page', () => {
        const wrapper = mountCrumbs([
            { label: 'Base de données', to: '/base-de-donnees' },
            { label: 'Montures', to: '/base-de-donnees/montures' },
        ]);

        const current = wrapper.find('[aria-current="page"]');

        expect(current.text()).toBe('Montures');
        expect(wrapper.findAll('[aria-current]')).toHaveLength(1);
    });

    it('renders a crumb without destination as plain text', () => {
        const wrapper = mountCrumbs([
            { label: 'Base de données' },
            { label: 'Montures', to: '/base-de-donnees/montures' },
        ]);

        expect(wrapper.findAll('a').map(a => a.attributes('href'))).toEqual(['/']);
        expect(wrapper.text()).toContain('Base de données');
    });

    it('hides the separators from assistive technologies', () => {
        const wrapper = mountCrumbs([{ label: 'FAQ', to: '/faq' }]);

        const separators = wrapper.findAll('[aria-hidden="true"]');

        expect(separators).toHaveLength(1);
        expect(separators[0].text()).toBe('/');
    });
});
