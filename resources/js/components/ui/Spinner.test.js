import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Spinner from './Spinner.vue';

describe('Spinner', () => {
    it('announces a wait to screen readers, in words', () => {
        const wrapper = mount(Spinner);

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.find('.sr-only').text()).toBe('Chargement…');
    });

    it('says what it waits for when told', () => {
        expect(mount(Spinner, { props: { label: 'Synchronisation avec Blizzard…' } }).find('.sr-only').text()).toBe('Synchronisation avec Blizzard…');
    });

    it.each([['sm', '16'], ['md', '20'], ['lg', '24']])('comes in the %s size', (size, pixels) => {
        expect(mount(Spinner, { props: { size } }).find('svg').attributes('width')).toBe(pixels);
    });

    it('spins only when motion is allowed', () => {
        expect(mount(Spinner).find('svg').classes()).toContain('motion-safe:animate-spin');
    });
});
