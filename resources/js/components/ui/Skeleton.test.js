import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Skeleton from './Skeleton.vue';

describe('Skeleton', () => {
    it('is silent for assistive technologies, the loading container speaks instead', () => {
        expect(mount(Skeleton).attributes('aria-hidden')).toBe('true');
    });

    it('keeps the size the caller gives it, to hold the room of the coming content', () => {
        const classes = mount(Skeleton, { attrs: { class: 'h-24 w-full' } }).classes();

        expect(classes).toEqual(expect.arrayContaining(['h-24', 'w-full', 'block']));
    });

    it('pulses only when motion is allowed', () => {
        const classes = mount(Skeleton).classes();

        expect(classes).toContain('motion-safe:animate-pulse');
        expect(classes).not.toContain('animate-pulse');
    });

    it.each([['block', 'rounded-ui-md'], ['text', 'h-4'], ['circle', 'rounded-full']])('draws a %s shape', (shape, marker) => {
        expect(mount(Skeleton, { props: { shape } }).classes()).toContain(marker);
    });

    it('warns about a shape outside the list and draws a block', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const classes = mount(Skeleton, { props: { shape: 'star' } }).classes();

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "shape"');
        expect(classes).toContain('rounded-ui-md');
        warn.mockRestore();
    });
});
