import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mutable auth state, read through the mocked usePage.
const pageState = { url: '/', props: { auth: { isAuthenticated: false, isAdmin: false } } };

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => pageState,
    router: { visit: vi.fn(), on: vi.fn() },
}));

import HomePage from './HomePage.vue';
import { mountWithPlugins } from '../tests/helpers';
import { expectNoAxeViolations } from '../tests/axe';

const meta = {
    title: 'WowPlanet - Suivi de progression World of Warcraft',
    description: 'Suivi de progression WoW',
    ogTitle: 'WowPlanet',
    ogDescription: 'Suivi de progression WoW',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/',
    ogType: 'website',
    canonicalUrl: 'https://example.com/',
    jsonLd: null,
};

const counts = {
    mounts: 1663,
    achievements: 8555,
    quests: 22088,
    pets: 2177,
    decors: 2077,
    appearances: 49048,
    professions: 14,
    recipes: 12427,
};

const mountHomePage = (props = {}) => mountWithPlugins(HomePage, { props: { meta, counts, ...props } });

const SECTION_PATHS = [
    '/base-de-donnees/montures',
    '/base-de-donnees/hauts-faits',
    '/base-de-donnees/quetes',
    '/base-de-donnees/mascottes',
    '/base-de-donnees/decorations',
    '/base-de-donnees/garde-robe',
    '/base-de-donnees/professions',
];

describe('HomePage', () => {
    beforeEach(() => {
        pageState.props.auth = { isAuthenticated: false, isAdmin: false };
    });

    it('carries the main keyword in its single first-level heading', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.findAll('h1').map((heading) => heading.text())).toEqual(['Suivez votre progression World of Warcraft']);
    });

    it('keeps its pitch, the wardrobe included', async () => {
        const wrapper = await mountHomePage();
        const pitch = wrapper.find('h1 + p').text();

        expect(pitch).toContain('l’API Blizzard');
        expect(pitch).toContain('garde-robe');
    });

    it('asks a visitor to log in with Battle.net, with a full page load', async () => {
        const wrapper = await mountHomePage();
        const login = wrapper.find('[data-hero-actions] a[href="/auth/blizzard/redirect"]');

        expect(login.text()).toContain('Se connecter avec Battle.net');
        expect(wrapper.findAllComponents({ name: 'Link' }).some((link) => link.props('href') === '/auth/blizzard/redirect')).toBe(false);
    });

    it('opens the account to a member instead', async () => {
        pageState.props.auth = { isAuthenticated: true, isAdmin: false };
        const wrapper = await mountHomePage();

        expect(wrapper.find('[data-hero-actions] a[href="/mon-compte"]').text()).toContain('Mon compte');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });

    it('offers the database next to the main action', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.find('[data-hero-actions] a[href="/base-de-donnees"]').exists()).toBe(true);
    });

    it('counts what it tracks from the server, never from written figures', async () => {
        const wrapper = await mountHomePage();
        const tiles = wrapper.findAll('[data-tracked]').map((tile) => tile.text().replace(/\s/g, ' '));

        expect(tiles).toHaveLength(4);
        expect(tiles.join(' | ')).toMatch(/22.088/);
        expect(tiles.join(' | ')).toMatch(/1.663/);
        expect(wrapper.text()).not.toMatch(/1\s?569|21\s?000|8\s?600|2\s?117/);
    });

    it('shows a dash rather than a made-up number when a count is missing', async () => {
        const wrapper = await mountHomePage({ counts: {} });

        expect(wrapper.findAll('[data-tracked]')[0].text()).toContain('—');
    });

    it('opens on the seven database sections, the wardrobe included, and on the PvP ladders', async () => {
        const wrapper = await mountHomePage();
        const hrefs = wrapper.findAll('[data-explore] a').map((link) => link.attributes('href'));

        expect(hrefs).toEqual([...SECTION_PATHS, '/classements-pvp']);
    });

    it('edges each database section with the colour of its dimension', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.find('[data-explore] li').attributes('style')).toContain('border-top-color');
    });

    it('invites to the Discord server', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.findComponent({ name: 'DiscordInvite' }).exists()).toBe(true);
    });

    it('credits the official Blizzard API', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.text()).toContain('API officielle Blizzard');
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountHomePage();

        await expectNoAxeViolations(wrapper.element);
    });
});
