import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatTile from './StatTile.vue';

const mountTile = (props = {}) => mount(StatTile, { props: { label: 'Montures', value: 412, ...props } });

describe('StatTile', () => {
    it('shows its label and its value, the value in tabular figures', () => {
        const wrapper = mountTile();

        expect(wrapper.find('dt').text()).toBe('Montures');
        expect(wrapper.find('[data-stat-value]').text()).toBe('412');
        expect(wrapper.find('[data-stat-value]').classes()).toContain('tabular-nums');
    });

    it('accepts an already formatted value', () => {
        expect(mountTile({ value: '61,2' }).find('[data-stat-value]').text()).toBe('61,2');
    });

    it('adds a suffix when given one', () => {
        expect(mountTile({ value: 87, suffix: '%' }).find('dd').text()).toContain('87\u202f%');
    });

    it('has no variation unless given one', () => {
        expect(mountTile().find('[data-stat-delta]').exists()).toBe(false);
    });

    it('shows a rise with its sign, an arrow and the success colour', () => {
        const delta = mountTile({ delta: 12 }).find('[data-stat-delta]');

        expect(delta.text()).toContain('+12');
        expect(delta.classes()).toContain('text-success');
        expect(delta.find('svg').exists()).toBe(true);
    });

    it('shows a fall with a true minus sign and the danger colour', () => {
        const delta = mountTile({ delta: -1500 }).find('[data-stat-delta]');

        expect(delta.text()).toContain('−1 500');
        expect(delta.classes()).toContain('text-danger');
    });

    it('shows no change in the muted colour', () => {
        const delta = mountTile({ delta: 0 }).find('[data-stat-delta]');

        expect(delta.text()).toContain('0');
        expect(delta.classes()).toContain('text-muted');
    });

    it('says what the variation is for screen readers, not only with colour', () => {
        expect(mountTile({ delta: 12 }).find('[data-stat-delta] .sr-only').text()).toBe('en hausse de');
        expect(mountTile({ delta: -3 }).find('[data-stat-delta] .sr-only').text()).toBe('en baisse de');
        expect(mountTile({ delta: 0 }).find('[data-stat-delta] .sr-only').text()).toBe('sans changement,');
    });
});

describe('StatTile colour', () => {
    it('stays neutral by default', () => {
        const wrapper = mount(StatTile, { props: { label: 'Montures', value: 739 } });

        expect(wrapper.find('[data-stat-value]').attributes('style')).toBeUndefined();
        expect(wrapper.attributes('style')).toBeUndefined();
    });

    it('writes the value in the given colour, over a rule in the accent colour', () => {
        const wrapper = mount(StatTile, { props: { label: 'Montures', value: 739, valueColor: '#E86FB8', ruleColor: '#DD3CA7' } });

        expect(wrapper.find('[data-stat-value]').attributes('style')).toContain('#E86FB8');
        expect(wrapper.attributes('style')).toContain('#DD3CA7');
        expect(wrapper.classes()).toContain('border-t-4');
    });
});
