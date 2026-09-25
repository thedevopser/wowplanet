import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const theme = vi.hoisted(() => ({ effective: null }));

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');
    theme.effective = ref('dark');

    return { useTheme: () => ({ effective: theme.effective }) };
});

import { classColor, factionColor } from '../utils/wowColors';
import { useFavoriteStore } from '../stores/favorites';
import { useTaskStore } from '../stores/tasks';
import { useToastStore } from '../stores/toasts';
import CharacterCard from './CharacterCard.vue';

const baseCharacter = {
    name: 'Arthas',
    level: 80,
    race: 'Humain',
    class: 'Chevalier de la mort',
    classId: 6,
    realm: 'Hyjal',
    guild: 'Les Chevaliers',
    avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
    faction: 'Alliance',
    mountsCount: 150,
    petsCount: 200,
    score: { global: 42.5, rank: 'Rare' },
};

let wrapper;

async function mountCard({ character = baseCharacter, isOwner = false, favorites = [], stubActions = true } = {}) {
    wrapper = mount(CharacterCard, {
        props: { character, realm: 'hyjal', name: 'arthas', isOwner },
        global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, stubActions, initialState: { favorites: { favorites } } })],
        },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const button = (label) => wrapper.findAll('button').find((candidate) => candidate.text().includes(label));

beforeEach(() => {
    theme.effective.value = 'dark';
});

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('CharacterCard', () => {
    it('names the character in the h1, with the guild beside', async () => {
        await mountCard();

        expect(wrapper.find('h1').text()).toContain('Arthas');
        expect(wrapper.text()).toContain('Les Chevaliers');
    });

    it('writes the name in the readable colour of the class', async () => {
        await mountCard();
        expect(wrapper.find('h1').attributes('style')).toContain(classColor(6).onDark);

        theme.effective.value = 'light';
        await flushPromises();

        expect(wrapper.find('h1').attributes('style')).toContain(classColor(6).onLight);
    });

    it('describes level, race, class and realm', async () => {
        await mountCard();

        expect(wrapper.text()).toContain('Niveau 80');
        expect(wrapper.text()).toContain('Humain');
        expect(wrapper.text()).toContain('Chevalier de la mort');
        expect(wrapper.text()).toContain('Hyjal');
    });

    it('draws a rule in the base colour of the class', async () => {
        await mountCard();

        expect(wrapper.find('[data-class-rule]').attributes('style')).toContain((classColor(6).base));
    });

    it('writes the class in its readable colour for the theme', async () => {
        await mountCard();
        expect(wrapper.find('[data-class-name]').attributes('style')).toContain((classColor(6).onDark));

        theme.effective.value = 'light';
        await flushPromises();

        expect(wrapper.find('[data-class-name]').attributes('style')).toContain((classColor(6).onLight));
    });

    it('shows the faction as a badge', async () => {
        await mountCard();

        const badge = wrapper.find('[data-faction]');

        expect(badge.text()).toBe('Alliance');
        expect(badge.attributes('style')).toContain((factionColor('ALLIANCE').onDark));
    });

    it('shows an unknown faction or class without failing', async () => {
        await mountCard({ character: { ...baseCharacter, faction: 'Neutre', classId: 99 } });

        expect(wrapper.find('[data-faction]').text()).toBe('Neutre');
        expect(wrapper.find('h1').exists()).toBe(true);
    });

    it('puts the global score forward in a ring coloured by its rank', async () => {
        await mountCard();

        const ring = wrapper.findComponent({ name: 'ScoreBadge' });

        expect(ring.props()).toEqual({ score: 42.5, rank: 'Rare' });
        expect(wrapper.findComponent({ name: 'StatTile' }).exists()).toBe(false);
    });

    it('leaves the counters to the overview', async () => {
        await mountCard();

        expect(wrapper.text()).not.toContain('150');
        expect(wrapper.text()).not.toContain('Montures');
    });

    it('offers no action on the sheet of another player', async () => {
        await mountCard({ isOwner: false });

        expect(button('Favori')).toBeUndefined();
        expect(button('Ajouter une tâche')).toBeUndefined();
    });

    describe('for the owner', () => {
        it('fetches the favourites to know the state of the star', async () => {
            await mountCard({ isOwner: true });

            expect(useFavoriteStore().fetchFavorites).toHaveBeenCalled();
        });

        it('reflects the favourite state in a pressed button', async () => {
            await mountCard({ isOwner: true, favorites: [{ realm_slug: 'hyjal', character_name: 'arthas' }] });

            expect(button('Favori').attributes('aria-pressed')).toBe('true');
        });

        it('toggles the favourite', async () => {
            await mountCard({ isOwner: true });

            await button('Favori').trigger('click');

            expect(useFavoriteStore().toggleFavorite).toHaveBeenCalledWith('hyjal', 'arthas');
            expect(button('Favori').attributes('aria-pressed')).toBe('false');
        });

        it('explains what to remove once three favourites are pinned', async () => {
            const favorites = [1, 2, 3].map((id) => ({ realm_slug: 'hyjal', character_name: `alt${id}` }));
            await mountCard({ isOwner: true, favorites, stubActions: false });

            await button('Favori').trigger('click');
            await flushPromises();

            expect(useToastStore().items).toEqual([expect.objectContaining({
                title: 'Trois favoris au maximum',
                tone: 'warning',
                action: { label: 'Gérer mes favoris', href: '/mon-compte' },
            })]);
        });

        it('opens the task panel on this character', async () => {
            await mountCard({ isOwner: true });

            await button('Ajouter une tâche').trigger('click');

            expect(useTaskStore().openFor).toHaveBeenCalledWith('hyjal', 'arthas');
        });
    });
});
