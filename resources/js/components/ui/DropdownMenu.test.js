import { describe, it, expect, vi, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { LogOut, User } from 'lucide-vue-next';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import DropdownMenu from './DropdownMenu.vue';

const ITEMS = [
    { key: 'account', label: 'Mon compte', href: '/mon-compte', icon: User },
    { key: 'logout', label: 'Déconnexion', icon: LogOut, tone: 'danger' },
    { key: 'favorites', label: 'Mes favoris', href: '/mes-favoris' },
];

let wrapper;

async function mountMenu(items = ITEMS, extraProps = {}) {
    wrapper = mount(DropdownMenu, {
        props: { items, label: 'Menu du compte', ...extraProps },
        slots: { trigger: '<button type="button" data-trigger>Arthas</button>' },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const trigger = () => document.querySelector('[data-trigger]');
const menu = () => document.querySelector('[role="menu"]');
const menuItems = () => [...document.querySelectorAll('[role="menuitem"]')];

async function openWithKeyboard(key = 'Enter') {
    trigger().focus();
    trigger().dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
    await flushPromises();
}

async function press(key) {
    document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
    await flushPromises();
}

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('DropdownMenu', () => {
    it('stays closed until its trigger is used', async () => {
        await mountMenu();

        expect(menu()).toBeNull();
        expect(trigger().getAttribute('aria-haspopup')).toBe('menu');
        expect(trigger().getAttribute('aria-expanded')).toBe('false');
    });

    it.each(['Enter', ' ', 'ArrowDown'])('opens from the keyboard with %j', async (key) => {
        await mountMenu();

        await openWithKeyboard(key);

        expect(menu()).not.toBeNull();
        expect(menu().getAttribute('aria-label')).toBe('Menu du compte');
    });

    it('moves through its items with the arrows', async () => {
        await mountMenu();
        await openWithKeyboard();

        await press('ArrowDown');
        const first = document.activeElement.textContent.trim();
        await press('ArrowDown');

        expect(document.activeElement.textContent.trim()).not.toBe(first);
        expect(menuItems().map((item) => item.textContent.trim())).toContain(document.activeElement.textContent.trim());
    });

    // As for Dialog, Reka returns the focus to the trigger and happy-dom loses it again on
    // unmount: the return is checked by hand (see US-07), the wiring that enables it here.
    it('closes on Escape, its trigger reflecting the state', async () => {
        await mountMenu();
        await openWithKeyboard();

        expect(trigger().getAttribute('aria-expanded')).toBe('true');
        expect(trigger().getAttribute('aria-controls')).toBe(menu().id);

        await press('Escape');

        expect(menu()).toBeNull();
        expect(trigger().getAttribute('aria-expanded')).toBe('false');
    });

    it('sets destructive actions apart, last and behind a separator', async () => {
        await mountMenu();
        await openWithKeyboard();

        const labels = menuItems().map((item) => item.textContent.trim());
        const separator = menu().querySelector('[role="separator"]');

        expect(labels).toEqual(['Mon compte', 'Mes favoris', 'Déconnexion']);
        expect(separator).not.toBeNull();
        expect(separator.nextElementSibling.textContent.trim()).toBe('Déconnexion');
        expect(menuItems().at(-1).className).toContain('text-danger');
    });

    it('draws no separator when nothing is destructive', async () => {
        await mountMenu(ITEMS.filter((item) => item.tone !== 'danger'));
        await openWithKeyboard();

        expect(menu().querySelector('[role="separator"]')).toBeNull();
    });

    it('renders items with an address as links', async () => {
        await mountMenu();
        await openWithKeyboard();

        expect(menuItems()[0].tagName).toBe('A');
        expect(menuItems()[0].getAttribute('href')).toBe('/mon-compte');
    });

    it('reports the chosen action by its key', async () => {
        await mountMenu();
        await openWithKeyboard();

        menuItems().at(-1).click();
        await flushPromises();

        expect(wrapper.emitted('select')?.at(-1)).toEqual(['logout']);
    });

    it('warns about an item without key or with an unknown tone', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        await mountMenu([{ label: 'Sans clé' }, { key: 'x', label: 'X', tone: 'gold' }]);

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "items"');
        warn.mockRestore();
    });

    it('opens an external item in a new tab, outside the Inertia router', async () => {
        await mountMenu([{ key: 'discord', label: 'Discord', href: 'https://discord.gg/x', external: true }]);
        await openWithKeyboard();

        const item = menuItems()[0];

        expect(item.tagName).toBe('A');
        expect(item.getAttribute('href')).toBe('https://discord.gg/x');
        expect(item.getAttribute('target')).toBe('_blank');
        expect(item.getAttribute('rel')).toBe('noopener noreferrer');
    });

    describe('with an exclusive choice', () => {
        const choices = {
            label: 'Thème',
            options: [
                { value: 'system', label: 'Système' },
                { value: 'dark', label: 'Sombre' },
                { value: 'light', label: 'Clair' },
            ],
        };
        const radios = () => [...document.querySelectorAll('[role="menuitemradio"]')];

        it('lists the options as radio items, the current one checked', async () => {
            await mountMenu(ITEMS, { choices, choice: 'dark' });
            await openWithKeyboard();

            expect(radios().map((item) => item.textContent.trim())).toEqual(['Système', 'Sombre', 'Clair']);
            expect(radios().map((item) => item.getAttribute('aria-checked'))).toEqual(['false', 'true', 'false']);
        });

        it('names the group of options', async () => {
            await mountMenu(ITEMS, { choices, choice: 'dark' });
            await openWithKeyboard();

            const group = menu().querySelector('[role="group"]');

            expect(document.getElementById(group.getAttribute('aria-labelledby')).textContent.trim()).toBe('Thème');
        });

        it('reports the picked option', async () => {
            await mountMenu(ITEMS, { choices, choice: 'dark' });
            await openWithKeyboard();

            radios()[2].click();
            await flushPromises();

            expect(wrapper.emitted('update:choice')?.at(-1)).toEqual(['light']);
        });

        it('sets the choice apart from the other items', async () => {
            await mountMenu(ITEMS, { choices, choice: 'dark' });
            await openWithKeyboard();

            expect(menu().querySelectorAll('[role="separator"]')).toHaveLength(2);
        });
    });
});
