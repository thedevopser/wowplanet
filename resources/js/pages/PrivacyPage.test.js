import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/privacy', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import PrivacyPage from './PrivacyPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Politique de confidentialité | WowPlanet',
    description: 'Confidentialité WowPlanet',
    ogTitle: 'Confidentialité',
    ogDescription: 'Confidentialité WowPlanet',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/privacy',
    ogType: 'website',
    canonicalUrl: 'https://example.com/privacy',
    jsonLd: null,
};

function mountPrivacyPage() {
    return mountWithPlugins(PrivacyPage, { props: { meta } });
}

describe('PrivacyPage', () => {
    it('renders the privacy heading', async () => {
        const wrapper = await mountPrivacyPage();

        expect(wrapper.find('h1').text()).toContain('Politique de confidentialité');
    });

    it('contains RGPD rights section', async () => {
        const wrapper = await mountPrivacyPage();
        const text = wrapper.text();

        expect(text).toContain('Vos droits (RGPD)');
        expect(text).toContain("Droit d'accès");
        expect(text).toContain('Droit de suppression');
    });

    it('declares the theme preference cookie next to the technical ones', async () => {
        const wrapper = await mountPrivacyPage();
        const text = wrapper.text();

        expect(text).toContain('wowplanet-theme');
        expect(text).toContain('préférence de thème');
    });

    it('declares the Wowhead tooltips and the host that receives the visitor address', async () => {
        const wrapper = await mountPrivacyPage();
        const section = wrapper.find('#infobulles-wowhead');

        expect(section.text()).toContain('wow.zamimg.com');
        expect(section.text()).toContain('adresse IP');
    });

    it('declares the audience measurement, without cookie', async () => {
        const wrapper = await mountPrivacyPage();
        const section = wrapper.find('#mesure-daudience');

        expect(section.text()).toContain('Umami');
        expect(section.text()).toContain('aucun cookie');
    });

    it('no longer claims that nothing reaches a third party', async () => {
        const wrapper = await mountPrivacyPage();

        expect(wrapper.find('#transfert-de-donnees').text()).not.toContain('aucune donnée personnelle n\'est transmise à des tiers.');
        expect(wrapper.find('#transfert-de-donnees').text()).toContain('Wowhead');
    });

    it('dates the change of its text', async () => {
        const wrapper = await mountPrivacyPage();

        expect(wrapper.find('header time').attributes('datetime')).toBe('2026-09-25');
    });
});
