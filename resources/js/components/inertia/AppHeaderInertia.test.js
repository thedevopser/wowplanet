import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return {
        __page: page,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: { visit: vi.fn(), on: vi.fn(() => () => {}) },
    };
});

const theme = vi.hoisted(() => ({ effective: null, choice: null, toggle: null, setChoice: null }));

vi.mock('../../composables/useTheme', async () => {
    const { ref } = await import('vue');
    theme.effective = ref('dark');
    theme.choice = ref('system');
    theme.toggle = vi.fn();
    theme.setChoice = vi.fn();

    return { useTheme: () => ({ effective: theme.effective, choice: theme.choice, toggle: theme.toggle, setChoice: theme.setChoice }) };
});

import { __page, router } from '@inertiajs/vue3';
import { useCharacterStore } from '../../stores/character';
import { mountWithPlugins } from '../../tests/helpers';
import AppHeaderInertia from './AppHeaderInertia.vue';

let wrapper;

async function mountHeader(state = {}) {
    wrapper = await mountWithPlugins(AppHeaderInertia, {
        initialState: { character: state },
        attachTo: document.body,
    });

    return wrapper;
}

const MEMBER = { isAuthenticated: true, battletag: 'Thrall#1234' };

const mainNav = () => wrapper.find('nav[aria-label="Navigation principale"]');
const navHrefs = () => mainNav().findAll('a').map((a) => a.attributes('href'));
const currentEntry = () => mainNav().find('[aria-current="page"]');
const userMenuTrigger = () => wrapper.find('button[aria-haspopup="menu"]');
const menuItems = () => [...document.querySelectorAll('[role="menuitem"], [role="menuitemradio"]')];
const menuItem = (label) => menuItems().find((item) => item.textContent.trim() === label);

async function openUserMenu() {
    userMenuTrigger().element.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    await flushPromises();
}

async function openDrawer() {
    await wrapper.find('button[aria-label="Ouvrir le menu"]').trigger('click');
    await flushPromises();
}

const drawer = () => document.querySelector('[role="dialog"]');

beforeEach(() => {
    __page.url = '/';
    __page.props = {};
    theme.effective.value = 'dark';
    theme.choice.value = 'system';
    vi.clearAllMocks();
});

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('AppHeaderInertia', () => {
    it('links the logo to the home page without making it the page title', async () => {
        await mountHeader();

        expect(wrapper.find('a[href="/"] img').attributes('alt')).toBe('WowPlanet');
        expect(wrapper.find('h1').exists()).toBe(false);
    });

    it('names its main navigation', async () => {
        await mountHeader();

        expect(mainNav().exists()).toBe(true);
    });

    it('offers the public sections and the login to a visitor', async () => {
        await mountHeader();

        expect(navHrefs()).toEqual(['/base-de-donnees', '/classements-pvp']);
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').text()).toContain('Se connecter');
        expect(userMenuTrigger().exists()).toBe(false);
    });

    it('leaves the login to the drawer on narrow screens, through a wrapper that alone sets its display', async () => {
        await mountHeader();

        const login = wrapper.find('header > div > div a[href="/auth/blizzard/redirect"]');

        expect(login.element.parentElement.className).toBe('hidden sm:block');
        expect(login.classes()).not.toContain('hidden');
    });

    it('leaves the theme button to the drawer below 1 024 px, the same way', async () => {
        await mountHeader();

        const toggle = wrapper.find('button[aria-label="Passer en mode clair"]');

        expect(toggle.element.parentElement.className).toBe('hidden lg:block');
    });

    it('lets a visitor switch the theme', async () => {
        await mountHeader();

        await wrapper.find('button[aria-label="Passer en mode clair"]').trigger('click');

        expect(theme.toggle).toHaveBeenCalled();
    });

    it('labels the theme button after the effective theme', async () => {
        theme.effective.value = 'light';

        await mountHeader();

        expect(wrapper.find('button[aria-label="Passer en mode sombre"]').exists()).toBe(true);
    });

    it('adds the account entry for a member, in place of the login', async () => {
        await mountHeader(MEMBER);

        expect(navHrefs()).toEqual(['/base-de-donnees', '/classements-pvp', '/mon-compte']);
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });

    it('never shows more than three navigation entries', async () => {
        await mountHeader({ ...MEMBER, isAdmin: true });

        expect(navHrefs().length).toBeLessThanOrEqual(3);
    });

    it.each([
        ['/base-de-donnees/montures', '/base-de-donnees'],
        ['/classements-pvp/2v2', '/classements-pvp'],
        ['/mon-compte', '/mon-compte'],
        ['/mon-compte/score', '/mon-compte'],
        ['/mon-compte/classes', '/mon-compte'],
    ])('marks the entry of %s as the current page', async (url, expected) => {
        __page.url = url;

        await mountHeader(MEMBER);

        expect(currentEntry().attributes('href')).toBe(expected);
        expect(mainNav().findAll('[aria-current]')).toHaveLength(1);
    });

    it('marks the account entry on a sheet of the account', async () => {
        __page.url = '/character/hyjal/thrall';
        __page.props = { isOwner: true };

        await mountHeader(MEMBER);

        expect(currentEntry().attributes('href')).toBe('/mon-compte');
    });

    it('marks no entry on the sheet of another player', async () => {
        __page.url = '/character/hyjal/arthas';
        __page.props = { isOwner: false };

        await mountHeader(MEMBER);

        expect(currentEntry().exists()).toBe(false);
    });

    it('opens the user menu from a button that carries the battletag', async () => {
        await mountHeader(MEMBER);

        expect(userMenuTrigger().text()).toContain('Thrall');
        expect(userMenuTrigger().text()).not.toContain('#1234');
    });

    it('falls back to a generic label without battletag', async () => {
        await mountHeader({ isAuthenticated: true, battletag: '' });

        expect(userMenuTrigger().text()).toContain('Mon compte');
    });

    it('gathers Discord and the logout in the user menu, the account views living in the hub', async () => {
        await mountHeader(MEMBER);
        await openUserMenu();

        expect(menuItem('Mon score')).toBeUndefined();
        expect(menuItem('Mes classes')).toBeUndefined();
        expect(menuItem('Discord').getAttribute('target')).toBe('_blank');
        expect(menuItems().at(-1).textContent.trim()).toBe('Déconnexion');
    });

    it('reserves the administration to an administrator', async () => {
        await mountHeader(MEMBER);
        await openUserMenu();
        expect(menuItem('Administration')).toBeUndefined();
        wrapper.unmount();
        document.body.innerHTML = '';

        await mountHeader({ ...MEMBER, isAdmin: true });
        await openUserMenu();

        expect(menuItem('Administration').getAttribute('href')).toBe('/admin');
    });

    it('offers the three theme choices in the user menu', async () => {
        theme.choice.value = 'light';
        await mountHeader(MEMBER);
        await openUserMenu();

        const radios = [...document.querySelectorAll('[role="menuitemradio"]')];

        expect(radios.map((item) => item.textContent.trim())).toEqual(['Système', 'Sombre', 'Clair']);
        expect(radios[2].getAttribute('aria-checked')).toBe('true');

        radios[1].click();
        await flushPromises();

        expect(theme.setChoice).toHaveBeenCalledWith('dark');
    });

    it('logs out then visits the home page', async () => {
        await mountHeader(MEMBER);
        const store = useCharacterStore(wrapper.vm.$pinia);
        await openUserMenu();

        menuItem('Déconnexion').click();
        await flushPromises();

        expect(store.logout).toHaveBeenCalled();
        expect(router.visit).toHaveBeenCalledWith('/');
    });

    it('moves the navigation into a drawer on narrow screens', async () => {
        await mountHeader(MEMBER);
        await openDrawer();

        const links = [...drawer().querySelectorAll('nav a')].map((a) => a.getAttribute('href'));

        expect(links).toEqual(['/base-de-donnees', '/classements-pvp', '/mon-compte']);
    });

    it('draws the accent rule of the drawer on the current entry only', async () => {
        __page.url = '/classements-pvp';
        await mountHeader();
        await openDrawer();

        const [database, pvp] = [...drawer().querySelectorAll('nav a')];

        expect(database.className).toContain('border-transparent');
        expect(pvp.className).toContain('border-accent');
    });

    it('offers the login and the theme in the drawer of a visitor', async () => {
        await mountHeader();
        await openDrawer();

        expect(drawer().querySelector('a[href="/auth/blizzard/redirect"]')).not.toBeNull();
        expect(drawer().textContent).toContain('Mode clair');
    });

    it('closes the drawer when the page changes', async () => {
        await mountHeader();
        await openDrawer();

        __page.url = '/base-de-donnees';
        await flushPromises();

        expect(drawer()).toBeNull();
    });

    it('keeps the drawer open when only the query string changes', async () => {
        await mountHeader();
        await openDrawer();

        __page.url = '/?page=2';
        await flushPromises();

        expect(drawer()).not.toBeNull();
    });
});
