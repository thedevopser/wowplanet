import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/mon-compte', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { nextTick } from 'vue';
import { mountWithPlugins } from '../../tests/helpers';
import CharactersView from './CharactersView.vue';
import { useCharacterStore } from '../../stores/character';
import { useFavoriteStore } from '../../stores/favorites';

const userCharacters = [
    { name: 'Arthas', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, classId: 6, className: 'Chevalier de la mort', raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Thrall', realmSlug: 'dalaran', realm: 'Dalaran', level: 70, classId: 7, className: 'Chaman', raceName: 'Orc', faction: 'Horde', avatarUrl: '' },
    { name: 'Jaina', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, classId: 8, className: 'Mage', raceName: 'Humaine', faction: 'Alliance', avatarUrl: '' },
];

const mountPage = async (storeState = {}, favorites = []) => {
    const wrapper = await mountWithPlugins(CharactersView, {
        initialState: {
            character: { userCharacters: [], loadingCharacters: false, ...storeState },
            favorites: { favorites, loading: false },
        },
    });
    if (storeState.userCharacters) {
        const store = useCharacterStore();
        store.userCharacters = storeState.userCharacters;
        store.loadingCharacters = storeState.loadingCharacters ?? false;
        await nextTick();
    }
    return wrapper;
};

describe('CharactersView', () => {
    it('renders the page title', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Mes personnages');
    });

    it('holds the room of the characters while they load', async () => {
        const wrapper = await mountPage({ loadingCharacters: true });

        expect(wrapper.find('[role="status"][aria-busy="true"]').text()).toContain('Chargement de vos personnages');
        expect(wrapper.findComponent({ name: 'LoadingSpinner' }).exists()).toBe(false);
    });

    it('displays all characters', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).toContain('Jaina');
    });

    it('displays character details (level, race, class)', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Niveau 80');
        expect(wrapper.text()).toContain('Humain');
        expect(wrapper.text()).toContain('Chevalier de la mort');
    });

    it('filters characters by search input', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('Arthas');

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).not.toContain('Thrall');
        expect(wrapper.text()).not.toContain('Jaina');
    });

    it('filters by realm name', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('dalaran');

        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).not.toContain('Arthas');
    });

    it('shows empty state when no characters match search', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('zzzzz');

        expect(wrapper.text()).toContain('Aucun personnage ne correspond');
    });

    it('explains an account without characters instead of offering to log in', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Aucun personnage sur ce compte');
        expect(wrapper.text()).toContain('niveau 10');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });

    it('offers to retry when the characters cannot be fetched', async () => {
        const wrapper = await mountPage({ error: 'Impossible de récupérer vos personnages' });
        const store = useCharacterStore();
        store.fetchUserCharacters.mockClear();

        expect(wrapper.find('[role="alert"]').text()).toContain('Impossible de récupérer vos personnages');
        await wrapper.find('[role="alert"] button').trigger('click');

        expect(store.fetchUserCharacters).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).not.toContain('Aucun personnage sur ce compte');
    });

    // ─── Sort and grouping ────────────────────────────────

    const allNames = (wrapper) => wrapper.findAll('[data-name]').map((name) => name.text());

    it('sorts by name by default, from a labelled list', async () => {
        const wrapper = await mountPage({ userCharacters });
        const select = wrapper.findComponent({ name: 'Select' });

        expect(select.props('label')).toBe('Trier par');
        expect(select.props('options').map((option) => option.label)).toEqual(['Nom', 'Niveau', 'Classe', 'Royaume']);
        expect(allNames(wrapper)).toEqual(['Arthas', 'Jaina', 'Thrall']);
    });

    it('sorts by level, highest first', async () => {
        const wrapper = await mountPage({ userCharacters: [...userCharacters, { ...userCharacters[0], name: 'Uther', level: 85 }] });

        await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'level');

        expect(allNames(wrapper)).toEqual(['Uther', 'Arthas', 'Jaina', 'Thrall']);
    });

    it('groups the characters by class on demand, under a heading per class', async () => {
        const wrapper = await mountPage({ userCharacters });
        const toggle = wrapper.find('button[aria-pressed]:not([aria-label])');

        expect(toggle.text()).toContain('Regrouper par classe');
        expect(wrapper.findAll('h3')).toHaveLength(0);

        await toggle.trigger('click');

        expect(toggle.attributes('aria-pressed')).toBe('true');
        expect(wrapper.findAll('h3').map((heading) => heading.text())).toEqual(['Chaman 1', 'Chevalier de la mort 1', 'Mage 1']);
    });

    // ─── Cross-character data ─────────────────────────────

    it('tells that the cross-character data is being computed', async () => {
        const wrapper = await mountPage({ userCharacters, crossCharacterStatus: 'loading' });

        expect(wrapper.find('[data-cross-status]').attributes('role')).toBe('status');
        expect(wrapper.find('[data-cross-status]').text()).toContain('Calcul des données croisées');
    });

    it('says nothing when the cross-character data was already up to date', async () => {
        const wrapper = await mountPage({ userCharacters, crossCharacterStatus: 'ready' });

        expect(wrapper.find('[data-cross-status]').exists()).toBe(false);
    });

    it('confirms briefly that a computation seen on screen has finished', async () => {
        vi.useFakeTimers();
        const wrapper = await mountPage({ userCharacters, crossCharacterStatus: 'loading' });
        const store = useCharacterStore();

        store.crossCharacterStatus = 'ready';
        await nextTick();
        expect(wrapper.find('[data-cross-status]').text()).toContain('Données croisées à jour');

        vi.advanceTimersByTime(5000);
        await nextTick();
        expect(wrapper.find('[data-cross-status]').exists()).toBe(false);
        vi.useRealTimers();
    });

    it('says nothing of the cross-character data while there is no character', async () => {
        const wrapper = await mountPage({ crossCharacterStatus: 'loading' });

        expect(wrapper.find('[data-cross-status]').exists()).toBe(false);
    });

    it('offers to relaunch a failed cross-character computation', async () => {
        const wrapper = await mountPage({ userCharacters, crossCharacterStatus: 'error' });
        const store = useCharacterStore();
        store.computeCrossCharacter.mockClear();

        expect(wrapper.find('[data-cross-status]').text()).toContain('n’ont pas pu être calculées');
        await wrapper.find('[data-cross-status] button').trigger('click');

        expect(store.computeCrossCharacter).toHaveBeenCalledTimes(1);
    });

    it('calls fetchUserCharacters on mount when empty', async () => {
        await mountPage();
        const store = useCharacterStore();

        expect(store.fetchUserCharacters).toHaveBeenCalled();
    });

    it('displays faction badge with correct color', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Alliance');
        expect(wrapper.text()).toContain('Horde');
    });

    it('filters characters by faction', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('horde');

        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).not.toContain('Arthas');
        expect(wrapper.text()).not.toContain('Jaina');
    });

    // ─── Favorites ────────────────────────────────────────

    const favorite = (realm, name, sortOrder = 0) => ({
        id: sortOrder + 1,
        realm_slug: realm,
        character_name: name,
        sort_order: sortOrder,
    });

    it('hides both section headings when there is no favorite', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).not.toContain('Favoris');
        expect(wrapper.text()).not.toContain('Tous mes personnages');
    });

    it('shows a favorites section with the counter', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        expect(wrapper.text()).toContain('Favoris');
        expect(wrapper.text()).toContain('1/3');
        expect(wrapper.text()).toContain('Tous mes personnages');
    });

    it('removes favorites from the main list', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        const sections = wrapper.findAll('section');
        expect(sections).toHaveLength(2);
        expect(sections[0].text()).toContain('Jaina');
        expect(sections[1].text()).not.toContain('Jaina');
        expect(sections[1].text()).toContain('Arthas');
        expect(sections[1].text()).toContain('Thrall');
    });

    it('keeps favorites in the order they were starred', async () => {
        const wrapper = await mountPage(
            { userCharacters },
            [favorite('hyjal', 'jaina', 0), favorite('dalaran', 'thrall', 1)]
        );

        const names = wrapper.findAll('section')[0].findAll('[data-name]').map(el => el.text());
        expect(names).toEqual(['Jaina', 'Thrall']);
    });

    it('applies the search to both sections', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        await wrapper.find('input[placeholder*="Rechercher"]').setValue('arthas');

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).not.toContain('Jaina');
    });

    it('shows the no-results message when the search empties both sections', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        await wrapper.find('input[placeholder*="Rechercher"]').setValue('zzzzz');

        expect(wrapper.text()).toContain('Aucun personnage ne correspond');
    });

    it('disables the remaining stars once three characters are starred', async () => {
        const wrapper = await mountPage(
            { userCharacters },
            [favorite('hyjal', 'arthas', 0), favorite('dalaran', 'thrall', 1), favorite('hyjal', 'jaina', 2)]
        );

        expect(wrapper.findAll('section')).toHaveLength(1);
        expect(wrapper.text()).toContain('3/3');
    });

    it('toggles a favorite when the star is clicked', async () => {
        const wrapper = await mountPage({ userCharacters });
        const favoritesStore = useFavoriteStore();

        await wrapper.find('button[aria-label="Ajouter aux favoris"]').trigger('click');

        expect(favoritesStore.toggleFavorite).toHaveBeenCalledWith('hyjal', 'Arthas');
    });

    it('fetches favorites on mount when authenticated', async () => {
        await mountPage({ userCharacters, isAuthenticated: true });
        const favoritesStore = useFavoriteStore();

        expect(favoritesStore.fetchFavorites).toHaveBeenCalled();
    });
});
