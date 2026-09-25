import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('../../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import { dimensionColor } from '../../utils/wowColors';
import ProgressSummary from './ProgressSummary.vue';

const mountSummary = (props = {}) => mount(ProgressSummary, {
    props: { title: 'Quêtes', description: 'Progression de l’extension', completed: 25, total: 100, dimension: 'quests', ...props },
});

describe('ProgressSummary', () => {
    it('titles the sub-tab in a h2', () => {
        expect(mountSummary().find('h2').text()).toBe('Quêtes');
    });

    it('gives the rounded share done and the counts', () => {
        const wrapper = mountSummary({ completed: 1, total: 3 });

        expect(wrapper.find('[data-percent]').text()).toBe('33 %');
        expect(wrapper.text()).toContain('1 / 3');
    });

    it('fills a labelled bar in the colour of its dimension', () => {
        const bar = mountSummary().findComponent({ name: 'ProgressBar' });

        expect(bar.props('value')).toBe(25);
        expect(bar.props('max')).toBe(100);
        expect(bar.props('color')).toBe(dimensionColor('quests').base);
        expect(bar.props('ariaLabel')).toBe('Quêtes : 25 sur 100');
    });

    it('writes the share in the readable colour of the dimension', () => {
        expect(mountSummary().find('[data-percent]').attributes('style')).toContain(dimensionColor('quests').onDark);
    });

    it('stays at zero without anything to count', () => {
        const wrapper = mountSummary({ completed: 0, total: 0 });

        expect(wrapper.find('[data-percent]').text()).toBe('0 %');
        expect(wrapper.findComponent({ name: 'ProgressBar' }).props('max')).toBe(1);
    });
});
