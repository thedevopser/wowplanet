import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/addons', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import AddonsPage from './AddonsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Addons WoW | WowPlanet',
    description: 'Addons WowPlanet',
    ogTitle: 'Addons WoW | WowPlanet',
    ogDescription: 'Addons WowPlanet',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/addons',
    ogType: 'website',
    canonicalUrl: 'https://example.com/addons',
    jsonLd: null,
};

function mountAddonsPage() {
    return mountWithPlugins(AddonsPage, { props: { meta } });
}

describe('AddonsPage', () => {
    it('renders the Addons heading', async () => {
        const wrapper = await mountAddonsPage();

        expect(wrapper.find('h1').text()).toContain('Addons');
    });

    it('presents both addons by name', async () => {
        const wrapper = await mountAddonsPage();
        const text = wrapper.text();

        expect(text).toContain('MapTidy');
        expect(text).toContain('WhatTodo');
        expect(text).toContain('TankTruckReverse');
    });

    it('renders accented text without raw HTML entities', async () => {
        const wrapper = await mountAddonsPage();
        const text = wrapper.text();

        expect(text).toContain('Liste de tâches à faire');
        expect(text).not.toContain('&agrave;');
        expect(text).not.toContain('&eacute;');
    });

    it('links to each addon on CurseForge', async () => {
        const wrapper = await mountAddonsPage();
        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));

        expect(hrefs).toContain('https://www.curseforge.com/wow/addons/maptidy');
        expect(hrefs).toContain('https://www.curseforge.com/wow/addons/whattodo');
        expect(hrefs).toContain('https://www.curseforge.com/wow/addons/tanks-truck-reverse');
    });
});
