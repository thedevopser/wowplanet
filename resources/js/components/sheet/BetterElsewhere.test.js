import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BetterElsewhere from './BetterElsewhere.vue';
import { mountWithPlugins } from '../../tests/helpers';

describe('BetterElsewhere', () => {
    it('names the other character and what it reached, in the warning tone', () => {
        const wrapper = mount(BetterElsewhere, { props: { character: 'Jaina', detail: 'Révéré' } });

        expect(wrapper.text()).toBe('Meilleur : Jaina — Révéré');
        expect(wrapper.classes()).toContain('text-warning');
    });

    it('carries an icon that assistive technologies skip', () => {
        expect(mount(BetterElsewhere, { props: { character: 'Jaina', detail: '95 / 100' } }).find('svg').attributes('aria-hidden')).toBe('true');
    });
});

describe('BetterElsewhere in english', () => {
    it('names the best character in english', async () => {
        const wrapper = await mountWithPlugins(BetterElsewhere, { locale: 'en', props: { character: 'Jaina', detail: 'Revered' } });

        expect(wrapper.text()).toBe('Best: Jaina — Revered');
    });
});
