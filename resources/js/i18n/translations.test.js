import { describe, it, expect, vi } from 'vitest';
import { createApp, h } from 'vue';
import { i18nVue, trans } from 'laravel-vue-i18n';
import { flushPromises, mount } from '@vue/test-utils';
import { browserI18nOptions, eagerI18nOptions } from './translations.js';

const ShowsLocaleName = { render() { return h('span', this.$t('locale.name')); } };

describe('eagerI18nOptions', () => {
    it.each([['fr', 'Français'], ['en', 'English']])('renders the %s texts', async (locale, text) => {
        const wrapper = mount(ShowsLocaleName, { global: { plugins: [{ install: (app) => i18nVue.install(app, eagerI18nOptions(locale)) }] } });
        await flushPromises();

        expect(wrapper.text()).toBe(text);
    });

    it('falls back to french without a locale', () => {
        expect(eagerI18nOptions(undefined).lang).toBe('fr');
    });
});

describe('browserI18nOptions', () => {
    it('downloads the language of the page, then signals the first load only once', async () => {
        const onFirstLoad = vi.fn();
        const options = browserI18nOptions('en', onFirstLoad);
        createApp({ render: () => null }).use(i18nVue, options);

        await vi.waitFor(() => expect(onFirstLoad).toHaveBeenCalledOnce());
        options.onLoad('fr');

        expect(onFirstLoad).toHaveBeenCalledOnce();
        expect(trans('locale.name')).toBe('English');
    });

    it('resolves nothing for a language without a file', () => {
        expect(browserI18nOptions('fr', vi.fn()).resolve('de')).toBeUndefined();
    });
});
