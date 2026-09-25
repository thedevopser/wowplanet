import { vi, describe, it, expect, beforeEach } from 'vitest';

// vi.mock is hoisted to the top of the file: the spies must be too.
const { routerGet, routerReload } = vi.hoisted(() => ({ routerGet: vi.fn(), routerReload: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/classements-pvp/3v3', props: {} }),
    router: { get: routerGet, reload: routerReload, visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../tests/helpers';
import PvpLeaderboardPage from './PvpLeaderboardPage.vue';
import { expectNoAxeViolations } from '../tests/axe';

const meta = {
    title: 'Classements PvP | WowPlanet',
    description: 'Classements PvP officiels',
    canonicalUrl: 'https://wowplanet.fr/classements-pvp',
    ogType: 'website',
    ogTitle: 'Classements PvP',
    ogDescription: 'Classements PvP officiels',
    ogImage: 'https://wowplanet.fr/images/og-default.png',
    ogUrl: 'https://wowplanet.fr/classements-pvp',
};

const groups = [
    {
        key: 'arena',
        label: 'Arène',
        brackets: [
            { slug: '2v2', label: 'Arène 2c2', short: 'Arène 2c2' },
            { slug: '3v3', label: 'Arène 3c3', short: 'Arène 3c3' },
        ],
    },
    {
        key: 'shuffle',
        label: 'Mêlée solo',
        // 40 brackets comme en production : le second niveau doit passer en liste déroulante.
        brackets: [
            { slug: 'shuffle-overall', label: 'Mêlée solo — toutes spés', short: 'Toutes spés' },
            ...Array.from({ length: 39 }, (_, i) => ({
                slug: `shuffle-spec-${i}`,
                label: `Mêlée solo — Spé ${i}`,
                short: `Spé ${i}`,
            })),
        ],
    },
];

const entries = [
    { rank: 1, name: 'Thrall', realm: 'Hyjal', realm_slug: 'hyjal', faction: 'HORDE', rating: 2999, won: 70, lost: 30 },
    { rank: 2, name: 'Jaina', realm: 'Dalaran', realm_slug: 'dalaran', faction: 'ALLIANCE', rating: 2950, won: 60, lost: 40 },
];

function mountPage(props = {}) {
    return mountWithPlugins(PvpLeaderboardPage, {
        props: {
            meta,
            groups,
            entries,
            bracket: '3v3',
            label: 'Arène 3c3',
            seasonId: 40,
            total: 2,
            currentPage: 1,
            lastPage: 1,
            unavailable: false,
            search: null,
            ...props,
        },
    });
}

describe('PvpLeaderboardPage', () => {
    beforeEach(() => {
        routerGet.mockClear();
        routerReload.mockClear();
    });

    it('renders one row per leaderboard entry', async () => {
        const wrapper = await mountPage();

        const rows = wrapper.findAll('[data-testid^="pvp-rank-"]');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Thrall');
        expect(rows[0].text()).toContain('Hyjal');
        expect(rows[0].text()).toContain('2999');
        expect(rows[0].text()).toContain('70');
    });

    it('links each entry to its character page', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="pvp-rank-1"] a').attributes('href'))
            .toBe('/character/hyjal/thrall');
    });

    it('shows one tab per mode and only the active mode brackets', async () => {
        const wrapper = await mountPage();

        expect(wrapper.findAll('[data-testid^="pvp-mode-"]')).toHaveLength(2);
        expect(wrapper.find('[data-testid="pvp-mode-arena"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('[data-testid="pvp-mode-shuffle"]').attributes('aria-pressed')).toBe('false');

        // Mode arène actif : ses deux brackets en boutons, aucun bracket de mêlée solo.
        expect(wrapper.findAll('[data-testid^="pvp-bracket-option-"]')).toHaveLength(2);
        expect(wrapper.find('[data-testid="pvp-bracket-select"]').exists()).toBe(false);
    });

    it('falls back to a dropdown when a mode has too many brackets', async () => {
        const wrapper = await mountPage({ bracket: 'shuffle-overall', label: 'Mêlée solo — toutes spés' });

        expect(wrapper.find('[data-testid="pvp-mode-shuffle"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.findAll('[data-testid^="pvp-bracket-option-"]')).toHaveLength(0);

        const select = wrapper.findComponent({ name: 'Select' });
        expect(select.props('label')).toBe('Spécialisation');
        expect(select.props('options')).toHaveLength(40);
        expect(select.props('modelValue')).toBe('shuffle-overall');
    });

    it('navigates when another bracket is selected', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="pvp-bracket-option-2v2"]').trigger('click');

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/2v2',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('navigates when a specialization is picked in the dropdown', async () => {
        const wrapper = await mountPage({ bracket: 'shuffle-overall' });

        await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'shuffle-spec-3');

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/shuffle-spec-3',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('switching mode loads that mode default bracket', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="pvp-mode-shuffle"]').trigger('click');

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/shuffle-overall',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('does not navigate when clicking the already active mode', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="pvp-mode-arena"]').trigger('click');

        expect(routerGet).not.toHaveBeenCalled();
    });

    it('requests the new page on pagination', async () => {
        const wrapper = await mountPage({ lastPage: 4, total: 200 });

        wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 3);
        await wrapper.vm.$nextTick();

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/3v3',
            expect.objectContaining({ page: 3 }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('requests a filtered page on debounced search', async () => {
        const wrapper = await mountPage();

        wrapper.findComponent({ name: 'SearchFilter' }).vm.$emit('search-debounced', 'thrall');
        await wrapper.vm.$nextTick();

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/3v3',
            expect.objectContaining({ page: 1, search: 'thrall' }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('shows the unavailable state', async () => {
        const wrapper = await mountPage({ unavailable: true, entries: [], total: 0 });

        const alert = wrapper.find('[role="alert"]');
        expect(alert.text()).toContain('indisponible');
        expect(wrapper.findAll('[data-testid^="pvp-rank-"]')).toHaveLength(0);

        await alert.find('button').trigger('click');
        expect(routerReload).toHaveBeenCalledTimes(1);
    });

    it('titles the page itself, with the season and the number of ranked players', async () => {
        const wrapper = await mountPage({ total: 5000 });

        expect(wrapper.find('h1').text()).toBe('Classements PvP');
        expect(wrapper.text()).toContain('Arène 3c3 — saison 40');
        expect(wrapper.text()).toMatch(/5\s000/);
        expect(wrapper.findComponent({ name: 'DatabasePageHeader' }).exists()).toBe(false);
    });

    it('lays the ranking out in a captioned table', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('table caption').text()).toBe('Classement Arène 3c3');
    });

    it('writes each player in the colour of their faction, unknown ones plainly', async () => {
        const wrapper = await mountPage({ entries: [...entries, { ...entries[0], rank: 3, name: 'Neutre', faction: 'NEUTRAL' }] });

        expect(wrapper.find('[data-testid="pvp-rank-1"] a').attributes('style')).toContain('color');
        expect(wrapper.find('[data-testid="pvp-rank-3"] a').attributes('style')).toBeUndefined();
    });

    it('spells out wins and losses instead of relying on colour', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="pvp-rank-1"]').text()).toContain('70 V / 30 D');
    });

    it('offers the pagination above and below the ranking', async () => {
        const wrapper = await mountPage({ lastPage: 4, total: 200 });

        expect(wrapper.findAllComponents({ name: 'DatabasePagination' }).map((pagination) => pagination.props('placement'))).toEqual(['top', 'bottom']);
    });

    it('shows an empty state when the search matches nothing', async () => {
        const wrapper = await mountPage({ entries: [], total: 0, search: 'personne' });

        expect(wrapper.text()).toContain('Aucun résultat');
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage();

        await expectNoAxeViolations(wrapper.element);
    });
});
