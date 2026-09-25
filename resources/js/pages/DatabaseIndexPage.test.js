import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import DatabaseIndexPage from './DatabaseIndexPage.vue';
import { mountWithPlugins } from '../tests/helpers';
import { expectNoAxeViolations } from '../tests/axe';

const meta = {
    title: 'Base de données WoW | WowPlanet',
    description: 'Base de données',
    ogTitle: 'Base de données',
    ogDescription: 'Base de données',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/base-de-donnees',
    ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees',
    jsonLd: null,
};

const counts = {
    mounts: 942,
    achievements: 5123,
    quests: 24000,
    pets: 1800,
    decors: 3200,
    appearances: 42000,
    recipes: 15000,
    professions: 14,
};

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseIndexPage, { props: { meta, counts, ...props } });
}

describe('DatabaseIndexPage', () => {
    it('renders heading "Base de données WoW"', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Base de données WoW');
    });

    it('displays counts from props', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('942');
        // toLocaleString('fr-FR') uses narrow no-break space (U+202F) as thousands separator
        expect(wrapper.text()).toMatch(/1\s*800/);
    });

    it('links the seven sections, the wardrobe included', async () => {
        const wrapper = await mountPage();
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/base-de-donnees/montures');
        expect(hrefs).toContain('/base-de-donnees/hauts-faits');
        expect(hrefs).toContain('/base-de-donnees/quetes');
        expect(hrefs).toContain('/base-de-donnees/mascottes');
        expect(hrefs).toContain('/base-de-donnees/decorations');
        expect(hrefs).toContain('/base-de-donnees/garde-robe');
        expect(hrefs).toContain('/base-de-donnees/professions');
        expect(wrapper.findAll('ul > li')).toHaveLength(7);
    });

    it('titles each section and edges it with its colour', async () => {
        const wrapper = await mountPage();

        expect(wrapper.findAll('h2').map((heading) => heading.text())).toEqual(
            ['Montures', 'Hauts-faits', 'Quêtes', 'Mascottes', 'Décorations', 'Garde-robe', 'Professions'],
        );
        expect(wrapper.find('ul > li').attributes('style')).toContain('border-top-color');
    });

    it('names the wardrobe in its introduction', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('h1 + p').text()).toContain('garde-robe');
    });

    it('renders placeholder when counts are missing', async () => {
        const wrapper = await mountPage({ counts: {} });
        expect(wrapper.text()).toContain('—');
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage();

        await expectNoAxeViolations(wrapper.element);
    });
});
