import { describe, it, expect } from 'vitest';
import { defineComponent, h } from 'vue';
import { mountWithPlugins } from '../tests/helpers';
import { useTranslate } from './useTranslate.js';

const ShowsLocaleName = defineComponent({
    setup() {
        const t = useTranslate();

        return () => h('span', t('locale.name'));
    },
});

describe('useTranslate', () => {
    it.each([['fr', 'Français'], ['en', 'English']])('translates in the language of the application (%s)', async (locale, text) => {
        expect((await mountWithPlugins(ShowsLocaleName, { locale })).text()).toBe(text);
    });
});
