import { describe, it, expect } from 'vitest';
import { defineComponent, h } from 'vue';
import { mountWithPlugins } from '../tests/helpers';
import { useActiveLocale } from './useActiveLocale.js';

const ShowsLocale = defineComponent({
    setup() {
        const locale = useActiveLocale();

        return () => h('span', locale.value);
    },
});

describe('useActiveLocale', () => {
    it('reads the language the texts are rendered in', async () => {
        expect((await mountWithPlugins(ShowsLocale, { locale: 'en' })).text()).toBe('en');
    });

});
