import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const theme = vi.hoisted(() => ({ effective: null }));

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');
    theme.effective = ref('dark');

    return { useTheme: () => ({ effective: theme.effective }) };
});

import { rankColor } from '../utils/wowColors';
import ScoreBadge from './ScoreBadge.vue';

const mountBadge = (props = {}) => mount(ScoreBadge, { props: { score: 25.8, rank: 'Commun', ...props } });

const progressCircle = (wrapper) => wrapper.findAll('circle')[1];

beforeEach(() => {
    theme.effective.value = 'dark';
});

describe('ScoreBadge', () => {
    it('is an image that tells the score and the rank', () => {
        const svg = mountBadge().find('[role="img"]');

        expect(svg.attributes('aria-label')).toBe('Score 25,8 sur 100, rang Commun');
    });

    it('draws the ring in the colour of the rank', () => {
        expect(progressCircle(mountBadge()).attributes('stroke')).toBe(rankColor('Commun').base);
        expect(progressCircle(mountBadge({ rank: 'Épique' })).attributes('stroke')).toBe(rankColor('Épique').base);
    });

    it('fills the ring in proportion to the score', () => {
        const circumference = 2 * Math.PI * 42;
        const offset = Number(progressCircle(mountBadge({ score: 25 })).attributes('stroke-dashoffset'));

        expect(offset).toBeCloseTo(circumference * 0.75);
    });

    it('writes the score as the score panel does, and the rank, in the readable colour of the rank', async () => {
        const wrapper = mountBadge();

        expect(wrapper.find('[data-score]').text()).toBe('25,8');
        expect(wrapper.find('[data-rank]').text()).toBe('Commun');
        expect(wrapper.find('[data-score]').attributes('style')).toContain(rankColor('Commun').onDark);

        theme.effective.value = 'light';
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-score]').attributes('style')).toContain(rankColor('Commun').onLight);
    });

    it('stays neutral for a rank it does not know', () => {
        const wrapper = mountBadge({ rank: 'Divin' });

        expect(progressCircle(wrapper).attributes('stroke')).toBe('var(--wp-accent)');
    });

    it('uses no raw palette class nor arbitrary size', () => {
        const html = mountBadge().html();

        expect(html).not.toMatch(/\b(?:text|bg)-slate-\d+\b/);
        expect(html).not.toMatch(/text-\[\d+px\]/);
    });
});
