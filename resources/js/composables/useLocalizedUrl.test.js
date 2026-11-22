import { describe, it, expect } from 'vitest';
import { defineComponent, h } from 'vue';
import { mountWithPlugins } from '../tests/helpers';
import { useLocalizedUrl } from './useLocalizedUrl.js';

const ShowsLink = defineComponent({
    setup() {
        const { locale, localizedUrl } = useLocalizedUrl();

        return () => h('a', { href: localizedUrl('/base-de-donnees/montures') }, locale.value);
    },
});

describe('useLocalizedUrl', () => {
    it('builds internal links in the language of the page', async () => {
        const wrapper = await mountWithPlugins(ShowsLink, { locale: 'en' });

        expect(wrapper.attributes('href')).toBe('/en/database/mounts');
        expect(wrapper.text()).toBe('en');
    });

    it('keeps the french links on a french page', async () => {
        const wrapper = await mountWithPlugins(ShowsLink, { locale: 'fr' });

        expect(wrapper.attributes('href')).toBe('/base-de-donnees/montures');
    });
});
