import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ props: {} });

    return { __page: page, usePage: () => page, router: { visit: vi.fn() }, Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } };
});

const rememberLocale = vi.hoisted(() => vi.fn());
vi.mock('../../i18n/localeChoice', () => ({ rememberLocale }));

import { __page, router } from '@inertiajs/vue3';
import { mountWithPlugins } from '../../tests/helpers';
import LanguageSwitcher from './LanguageSwitcher.vue';

const ALTERNATES = { fr: 'https://wowplanet.fr/faq', en: 'https://wowplanet.fr/en/faq' };
let wrapper;

beforeEach(() => {
    rememberLocale.mockClear();
    router.visit.mockClear();
});

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

async function mountOn(locale, alternateUrls = ALTERNATES) {
    Object.assign(__page, { props: { locale, alternateUrls } });
    wrapper = await mountWithPlugins(LanguageSwitcher, { locale, attachTo: document.body });

    return wrapper;
}

async function openMenu() {
    wrapper.get('button').element.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    await flushPromises();
}

const radios = () => [...document.querySelectorAll('[role="menuitemradio"]')];

describe('LanguageSwitcher', () => {
    it.each([['fr', 'Français', '/images/flags/fr.svg'], ['en', 'English', '/images/flags/gb.svg']])(
        'shows the flag and the name of the current language (%s)',
        async (locale, name, flag) => {
            const trigger = (await mountOn(locale)).get('button');

            expect(trigger.text()).toContain(name);
            expect(trigger.get('img').attributes('src')).toBe(flag);
        },
    );

    it('names the menu in the language of the page', async () => {
        expect((await mountOn('en')).get('button').attributes('aria-label')).toBe('Language: English');
    });

    it('lists both languages with their flag, the current one checked', async () => {
        await mountOn('fr');
        await openMenu();

        expect(radios().map((item) => item.textContent.trim())).toEqual(['Français', 'English']);
        expect(radios().map((item) => item.getAttribute('aria-checked'))).toEqual(['true', 'false']);
        expect(radios().every((item) => item.querySelector('img'))).toBe(true);
    });

    it('leads to the same page in the picked language and remembers the choice', async () => {
        await mountOn('fr');
        await openMenu();

        radios()[1].click();
        await flushPromises();

        expect(rememberLocale).toHaveBeenCalledWith('en');
        expect(router.visit).toHaveBeenCalledWith(ALTERNATES.en);
    });

    it('shows nothing on a page that exists in one language only', async () => {
        expect((await mountOn('fr', null)).find('button').exists()).toBe(false);
    });
});
