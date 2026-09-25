import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/mon-compte/classes', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../../tests/helpers';
import ClassesView from './ClassesView.vue';
import { useCharacterStore } from '../../stores/character';

const userCharacters = [
    { name: 'Arthas', classId: 6, className: 'Chevalier de la mort', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Bolvar', classId: 6, className: 'Chevalier de la mort', realmSlug: 'hyjal', realm: 'Hyjal', level: 70, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Thrall', classId: 7, className: 'Chaman', realmSlug: 'dalaran', realm: 'Dalaran', level: 80, raceName: 'Orc', faction: 'Horde', avatarUrl: '' },
    { name: 'Jaina', classId: 8, className: 'Mage', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humaine', faction: 'Alliance', avatarUrl: '' },
    { name: 'Khadgar', classId: 8, className: 'Mage', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Medivh', classId: 8, className: 'Mage', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
];

describe('ClassesView', () => {
    it('renders the page title', async () => {
        const wrapper = await mountWithPlugins(ClassesView, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Mes classes');
    });

    it('displays total character count', async () => {
        const wrapper = await mountWithPlugins(ClassesView, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('6');
    });

    it('shows loading spinner while loading', async () => {
        const wrapper = await mountWithPlugins(ClassesView, {
            initialState: { character: { userCharacters: [], loadingCharacters: true, classIcons: {} } },
        });

        expect(wrapper.find('[role="status"][aria-busy="true"]').text()).toContain('Chargement de vos classes');
        expect(wrapper.findComponent({ name: 'LoadingSpinner' }).exists()).toBe(false);
    });

    it('displays podium with top 3 classes', async () => {
        const wrapper = await mountWithPlugins(ClassesView, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Mage');
        expect(wrapper.text()).toContain('Chevalier de la mort');
        expect(wrapper.text()).toContain('Chaman');
    });

    const mountView = (state = {}) => mountWithPlugins(ClassesView, {
        initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {}, ...state } },
    });

    const classButtons = (wrapper) => wrapper.findAll('button[data-class]');

    it('ranks the classes by number of characters', async () => {
        const wrapper = await mountView();

        expect(classButtons(wrapper).map((button) => button.attributes('data-rank'))).toEqual(['1', '2', '3']);
        expect(wrapper.findAll('[data-class] [data-count]').map((count) => count.text())).toEqual(['3', '2', '1']);
    });

    it('lists the classes beyond the podium apart', async () => {
        const wrapper = await mountView({ userCharacters: [...userCharacters, { ...userCharacters[0], name: 'Uther', classId: 2, className: 'Paladin' }] });

        expect(wrapper.find('[data-other-classes]').text()).toContain('Paladin');
    });

    it('makes each class a collapsed button', async () => {
        const wrapper = await mountView();

        expect(classButtons(wrapper)).toHaveLength(3);
        classButtons(wrapper).forEach((button) => expect(button.attributes('aria-expanded')).toBe('false'));
        expect(wrapper.find('div[class*="cursor-pointer"]').exists()).toBe(false);
    });

    it('opens the characters of a class, linked to their sheets, highest level first', async () => {
        const wrapper = await mountView();
        const deathKnight = classButtons(wrapper).find((button) => button.text().includes('Chevalier de la mort'));

        await deathKnight.trigger('click');

        expect(deathKnight.attributes('aria-expanded')).toBe('true');
        const panel = wrapper.find(`#${deathKnight.attributes('aria-controls')}`);
        expect(panel.findAll('a').map((link) => link.attributes('href'))).toEqual(['/character/hyjal/arthas', '/character/hyjal/bolvar']);
    });

    it('closes the class when pressed again', async () => {
        const wrapper = await mountView();
        const mage = classButtons(wrapper)[0];

        await mage.trigger('click');
        await mage.trigger('click');

        expect(mage.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[data-class-detail]').exists()).toBe(false);
    });

    it('writes each class in its colour, and a class Blizzard adds later uncoloured', async () => {
        const wrapper = await mountView({ userCharacters: [...userCharacters, { ...userCharacters[0], name: 'Nouveau', classId: 99, className: 'Inconnue' }] });

        expect(classButtons(wrapper)[0].find('[data-class-name]').attributes('style')).toContain('color');
        const unknown = wrapper.findAll('[data-class-name]').find((name) => name.text() === 'Inconnue');
        expect(unknown.attributes('style')).toBeUndefined();
    });

    it('offers to retry when the characters cannot be fetched', async () => {
        const wrapper = await mountView({ userCharacters: [], error: 'Impossible de récupérer vos personnages' });
        const store = useCharacterStore();
        store.fetchUserCharacters.mockClear();

        await wrapper.find('[role="alert"] button').trigger('click');

        expect(store.fetchUserCharacters).toHaveBeenCalledTimes(1);
    });

    it('calls fetchClassIcons on mount', async () => {
        const wrapper = await mountWithPlugins(ClassesView, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });
        const store = useCharacterStore();

        expect(store.fetchClassIcons).toHaveBeenCalled();
    });

    it('shows empty state when no characters', async () => {
        const wrapper = await mountWithPlugins(ClassesView, {
            initialState: { character: { userCharacters: [], loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Aucun personnage sur ce compte');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });
});
