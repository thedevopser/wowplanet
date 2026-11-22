import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/cgu', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import CguPage from './CguPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Conditions générales d\'utilisation | WowPlanet',
    description: 'CGU WowPlanet',
    ogTitle: 'CGU',
    ogDescription: 'CGU WowPlanet',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/cgu',
    ogType: 'website',
    canonicalUrl: 'https://example.com/cgu',
    jsonLd: null,
};

function mountCguPage() {
    return mountWithPlugins(CguPage, { props: { meta } });
}

describe('CguPage', () => {
    it('renders the CGU heading', async () => {
        const wrapper = await mountCguPage();

        expect(wrapper.find('h1').text()).toContain("Conditions Générales d'Utilisation");
    });

    it('contains key legal sections', async () => {
        const wrapper = await mountCguPage();
        const text = wrapper.text();

        expect(text).toContain('Objet');
        expect(text).toContain('Description du service');
        expect(text).toContain('Propriété intellectuelle');
        expect(text).toContain('Droit applicable');
    });

    it('no longer lists a character search among the services', async () => {
        const wrapper = await mountCguPage();

        expect(wrapper.text()).not.toContain('Rechercher des personnages');
    });
});

describe('CguPage in english', () => {
    it('shows the terms in english and says the french version prevails', async () => {
        const wrapper = await mountWithPlugins(CguPage, { locale: 'en', props: { meta } });

        expect(wrapper.get('h1').text()).toBe('Terms of use');
        expect(wrapper.get('#objet h2').text()).toBe('1. Purpose');
        expect(wrapper.text()).toContain('The French version prevails');
        expect(wrapper.text()).not.toContain('Description du service');
    });

    it('links to the english privacy policy', async () => {
        const wrapper = await mountWithPlugins(CguPage, { locale: 'en', props: { meta } });

        expect(wrapper.find('a[href="/en/privacy"]').exists()).toBe(true);
    });
});
