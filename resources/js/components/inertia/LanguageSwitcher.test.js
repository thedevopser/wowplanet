import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ props: {} });

    return { __page: page, usePage: () => page, Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } };
});

const rememberLocale = vi.hoisted(() => vi.fn());
vi.mock('../../i18n/localeChoice', () => ({ rememberLocale }));

import { __page } from '@inertiajs/vue3';
import { mountWithPlugins } from '../../tests/helpers';
import LanguageSwitcher from './LanguageSwitcher.vue';

const ALTERNATES = { fr: 'https://wowplanet.fr/faq', en: 'https://wowplanet.fr/en/faq' };

beforeEach(() => {
    rememberLocale.mockClear();
});

async function mountOn(locale, alternateUrls = ALTERNATES) {
    Object.assign(__page, { props: { locale, alternateUrls } });

    return mountWithPlugins(LanguageSwitcher, { locale });
}

describe('LanguageSwitcher', () => {
    it('leads a french page to its english version', async () => {
        const link = (await mountOn('fr')).get('a');

        expect(link.attributes()).toMatchObject({ href: ALTERNATES.en, hreflang: 'en', lang: 'en', 'aria-label': 'Version anglaise' });
        expect(link.text()).toBe('EN');
    });

    it('leads an english page to its french version', async () => {
        const link = (await mountOn('en')).get('a');

        expect(link.attributes()).toMatchObject({ href: ALTERNATES.fr, hreflang: 'fr', 'aria-label': 'French version' });
        expect(link.text()).toBe('FR');
    });

    it('remembers the language the visitor switches to', async () => {
        await (await mountOn('fr')).get('a').trigger('click');

        expect(rememberLocale).toHaveBeenCalledWith('en');
    });

    it('shows nothing on a page that exists in one language only', async () => {
        expect((await mountOn('fr', null)).find('a').exists()).toBe(false);
    });
});
