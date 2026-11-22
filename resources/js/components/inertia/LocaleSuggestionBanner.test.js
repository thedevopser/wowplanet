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
import LocaleSuggestionBanner from './LocaleSuggestionBanner.vue';

const ALTERNATES = { fr: 'https://wowplanet.fr/faq', en: 'https://wowplanet.fr/en/faq' };

beforeEach(() => {
    rememberLocale.mockClear();
});

async function mountWith(locale, localeSuggestion) {
    Object.assign(__page, { props: { locale, localeSuggestion, alternateUrls: ALTERNATES } });

    return mountWithPlugins(LocaleSuggestionBanner, { locale });
}

describe('LocaleSuggestionBanner', () => {
    it('offers the english version in english to a reader of a french page', async () => {
        const banner = (await mountWith('fr', 'en')).get('[role="region"]');

        expect(banner.attributes('lang')).toBe('en');
        expect(banner.text()).toContain('This page is available in English.');
        expect(banner.get('a').attributes('href')).toBe(ALTERNATES.en);
    });

    it('offers the french version in french to a reader of an english page', async () => {
        const banner = (await mountWith('en', 'fr')).get('[role="region"]');

        expect(banner.text()).toContain('Cette page existe en français.');
        expect(banner.get('a').attributes('href')).toBe(ALTERNATES.fr);
    });

    it('remembers the suggested language when the visitor follows it', async () => {
        await (await mountWith('fr', 'en')).get('a').trigger('click');

        expect(rememberLocale).toHaveBeenCalledWith('en');
    });

    it('hides for good once the visitor keeps the language of the page', async () => {
        const wrapper = await mountWith('fr', 'en');

        await wrapper.get('button').trigger('click');

        expect(rememberLocale).toHaveBeenCalledWith('fr');
        expect(wrapper.find('[role="region"]').exists()).toBe(false);
    });

    it('shows nothing without a suggestion', async () => {
        expect((await mountWith('fr', null)).find('[role="region"]').exists()).toBe(false);
    });
});
