import { describe, it, expect, vi, afterEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/character/hyjal/arthas', props: {} }),
    router: { visit: vi.fn(), on: vi.fn(), push: vi.fn(), replace: vi.fn() },
}));

import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { LEGACY_PALETTE, mountWithPlugins } from '../tests/helpers';
import CharacterPage from './CharacterPage.vue';
import { expectNoAxeViolations } from '../tests/axe';

const characterData = {
    name: 'Arthas',
    level: 80,
    race: 'Humain',
    class: 'Chevalier de la mort',
    classId: 6,
    realm: 'Hyjal',
    avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
    classIconUrl: '',
    mountsCount: 150,
    petsCount: 200,
    mounts: [],
    pets: [],
    collections: {},
};

const meta = {
    title: 'Arthas - Chevalier de la mort 80 | Hyjal | WowPlanet',
    description: 'Profil du personnage Arthas',
    ogTitle: 'Arthas',
    ogDescription: 'Profil du personnage Arthas',
    ogImage: 'https://example.com/avatar.jpg',
    ogUrl: 'https://example.com/character/hyjal/arthas',
    ogType: 'profile',
    canonicalUrl: 'https://example.com/character/hyjal/arthas',
    jsonLd: null,
};

async function mountCharacterPage(character, { isOwner = false, isAuthenticated = false, section = null, sub = null } = {}) {
    const wrapper = await mountWithPlugins(CharacterPage, {
        props: { character, realm: 'hyjal', name: 'arthas', meta, isOwner, section, sub },
        initialState: { character: { isAuthenticated } },
        stubs: {
            CharacterOverview: true, QuestsTab: true, AchievementsTab: true, ReputationsTab: true, ProfessionsTab: true,
            MythicPlusTab: true, RaidsTab: true, PvpTab: true, EquipmentTab: true,
            CollectionTab: true, TransmogTab: true,
        },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const tabLists = (wrapper) => wrapper.findAll('[role="tablist"]');
const selectedIn = (list) => list.findAll('[role="tab"]').find((tab) => tab.attributes('aria-selected') === 'true');
const tabNamed = (list, label) => list.findAll('[role="tab"]').find((tab) => tab.text() === label);

afterEach(() => {
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('CharacterPage', () => {
    it('renders CharacterCard from the character prop', async () => {
        const wrapper = await mountCharacterPage(characterData);

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).toContain('Niveau 80');
    });

    it('offers the four sections of the sheet', async () => {
        const wrapper = await mountCharacterPage(characterData);

        const sections = tabLists(wrapper)[0];

        expect(sections.attributes('aria-label')).toBe('Sections de la fiche');
        expect(sections.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Aperçu', 'Progression', 'Endgame', 'Collections']);
    });

    it('opens the overview at the base address', async () => {
        const wrapper = await mountCharacterPage(characterData);

        expect(selectedIn(tabLists(wrapper)[0]).text()).toBe('Aperçu');
        expect(tabLists(wrapper)).toHaveLength(1);
        expect(wrapper.findComponent({ name: 'CharacterOverview' }).exists()).toBe(true);
    });

    it('opens the section and sub-tab given by the address', async () => {
        const wrapper = await mountCharacterPage(characterData, { section: 'collections', sub: 'montures' });

        const [sections, subs] = tabLists(wrapper);

        expect(selectedIn(sections).text()).toBe('Collections');
        expect(subs.attributes('aria-label')).toBe('Collections');
        expect(subs.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Montures', 'Mascottes', 'Décorations', 'Garde-robe']);
        expect(selectedIn(subs).text()).toBe('Montures');
        expect(wrapper.findComponent({ name: 'CollectionTab' }).props('kind')).toBe('mounts');
    });

    it.each([
        ['progression', 'quetes', 'QuestsTab'],
        ['progression', 'hauts-faits', 'AchievementsTab'],
        ['progression', 'reputations', 'ReputationsTab'],
        ['progression', 'metiers', 'ProfessionsTab'],
        ['endgame', 'mythique-plus', 'MythicPlusTab'],
        ['endgame', 'raids', 'RaidsTab'],
        ['endgame', 'pvp', 'PvpTab'],
        ['endgame', 'equipement', 'EquipmentTab'],
        ['collections', 'mascottes', 'CollectionTab'],
        ['collections', 'decorations', 'CollectionTab'],
        ['collections', 'garde-robe', 'TransmogTab'],
    ])('shows %s/%s with its own content', async (section, sub, component) => {
        const wrapper = await mountCharacterPage(characterData, { section, sub });

        expect(wrapper.findComponent({ name: component }).exists()).toBe(true);
    });

    it('writes a chosen section in the address and the history, without asking the server', async () => {
        const wrapper = await mountCharacterPage(characterData);

        await tabNamed(tabLists(wrapper)[0], 'Endgame').trigger('mousedown', { button: 0 });

        const visit = router.push.mock.calls.at(-1)[0];

        expect(visit.url).toBe('/character/hyjal/arthas/endgame/mythique-plus');
        expect(visit.preserveState).toBe(true);
        expect(visit.preserveScroll).toBe(true);
        expect(visit.props({ section: null, sub: null, keep: 1 })).toEqual({ section: 'endgame', sub: 'mythique-plus', keep: 1 });
    });

    it('writes a chosen sub-tab in the address', async () => {
        const wrapper = await mountCharacterPage(characterData, { section: 'endgame', sub: 'mythique-plus' });

        await tabNamed(tabLists(wrapper)[1], 'Raids').trigger('mousedown', { button: 0 });

        expect(router.push.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas/endgame/raids');
    });

    it('comes back to the base address for the overview', async () => {
        const wrapper = await mountCharacterPage(characterData, { section: 'endgame', sub: 'raids' });

        await tabNamed(tabLists(wrapper)[0], 'Aperçu').trigger('mousedown', { button: 0 });

        expect(router.push.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas');
    });

    it('keeps the counters out of the tab labels', async () => {
        const wrapper = await mountCharacterPage(characterData, { section: 'collections', sub: 'montures' });

        expect(tabLists(wrapper)[1].text()).not.toContain('150');
    });

    it('shows a not-found message when character prop is null', async () => {
        const wrapper = await mountCharacterPage(null);

        expect(wrapper.text()).toContain('Personnage introuvable');
    });

    it('titles a missing character with the only h1 of the page, and offers a way back', async () => {
        const wrapper = await mountCharacterPage(null);

        expect(wrapper.findAll('h1').map((h1) => h1.text())).toEqual(['Personnage introuvable']);
        expect(wrapper.find('a[href="/"]').exists()).toBe(true);
        expect(wrapper.find('[data-not-found]').html()).not.toMatch(LEGACY_PALETTE);
    });

    it('leads the breadcrumb through the account hub on an owned sheet', async () => {
        const wrapper = await mountCharacterPage(characterData, { isOwner: true, isAuthenticated: true });

        const crumbs = wrapper.find('nav[aria-label="Fil d’Ariane"]');

        expect(crumbs.text()).toContain('Mon compte');
        expect(crumbs.find('[aria-current="page"]').text()).toBe('Arthas');
    });

    it('leaves the account hub out of the breadcrumb of another player sheet', async () => {
        const wrapper = await mountCharacterPage(characterData, { isOwner: false, isAuthenticated: true });

        const crumbs = wrapper.find('nav[aria-label="Fil d’Ariane"]');

        expect(crumbs.text()).not.toContain('Mon compte');
        expect(crumbs.find('[aria-current="page"]').text()).toBe('Arthas');
    });

    it('names the character in the single h1 of the page', async () => {
        const wrapper = await mountCharacterPage(characterData);

        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.find('h1').text()).toContain('Arthas');
    });

    it('tells the owner about the cross-character data of the account', async () => {
        const owned = await mountCharacterPage(characterData, { isOwner: true });

        expect(owned.findComponent({ name: 'CrossDataBanner' }).props('isOwner')).toBe(true);
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountCharacterPage(characterData);

        await expectNoAxeViolations(wrapper.element);
    });
});
