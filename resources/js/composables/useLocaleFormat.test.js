import { describe, it, expect } from 'vitest';
import { defineComponent, h } from 'vue';
import { mountWithPlugins } from '../tests/helpers';
import { useLocaleFormat } from './useLocaleFormat.js';

const Formats = defineComponent({
    setup() {
        const { formatNumber, formatScore, formatDate, compareNames } = useLocaleFormat();

        return () => h('span', [
            formatNumber(12345),
            formatNumber(null),
            formatScore(72.46),
            formatDate('2026-11-22T10:00:00Z', { day: 'numeric', month: 'long', year: 'numeric' }),
            ['Éclair', 'Zèbre', 'Ange'].toSorted(compareNames).join(','),
        ].join('|'));
    },
});

describe('useLocaleFormat', () => {
    it('formats numbers, scores and dates the french way on a french page', async () => {
        const wrapper = await mountWithPlugins(Formats, { locale: 'fr' });

        expect(wrapper.text()).toBe('12 345|0|72,5|22 novembre 2026|Ange,Éclair,Zèbre');
    });

    it('formats numbers, scores and dates the english way on an english page', async () => {
        const wrapper = await mountWithPlugins(Formats, { locale: 'en' });

        expect(wrapper.text()).toBe('12,345|0|72.5|November 22, 2026|Ange,Éclair,Zèbre');
    });
});
