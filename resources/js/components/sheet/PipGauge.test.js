import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PipGauge from './PipGauge.vue';

const mountGauge = (props = {}) => mount(PipGauge, { props: { filled: 3, total: 8, color: '#0070DD', label: 'Normal : 3 boss vaincus sur 8', ...props } });

describe('PipGauge', () => {
    it('is an image described by its label', () => {
        const gauge = mountGauge();

        expect(gauge.attributes('role')).toBe('img');
        expect(gauge.attributes('aria-label')).toBe('Normal : 3 boss vaincus sur 8');
    });

    it('draws one pip per step, the first ones filled in the given colour', () => {
        const pips = mountGauge().findAll('[data-pip]');

        expect(pips).toHaveLength(8);
        expect(pips.filter((pip) => pip.attributes('data-pip') === 'filled')).toHaveLength(3);
        expect(pips[0].attributes('style')).toContain('#0070DD');
        expect(pips[3].attributes('style')).toBeUndefined();
    });

    it('never fills more pips than it has', () => {
        expect(mountGauge({ filled: 12, total: 8 }).findAll('[data-pip="filled"]')).toHaveLength(8);
    });
});
