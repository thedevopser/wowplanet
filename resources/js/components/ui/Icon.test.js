import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { Search } from 'lucide-vue-next';
import Icon from './Icon.vue';

const mountIcon = (props = {}) => mount(Icon, { props: { icon: Search, ...props } });

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Icon', () => {
    it('renders the given Lucide icon at the medium size by default', () => {
        const svg = mountIcon().find('svg');

        expect(svg.exists()).toBe(true);
        expect(svg.attributes('width')).toBe('20');
        expect(svg.attributes('height')).toBe('20');
    });

    it.each([['sm', '16'], ['md', '20'], ['lg', '24']])('renders the %s size at %s px', (size, pixels) => {
        const svg = mountIcon({ size }).find('svg');

        expect(svg.attributes('width')).toBe(pixels);
        expect(svg.attributes('height')).toBe(pixels);
    });

    it('draws a 2 px stroke whatever the size', () => {
        expect(mountIcon({ size: 'lg' }).find('svg').attributes('stroke-width')).toBe('2');
    });

    it('is hidden from assistive technologies by default', () => {
        const svg = mountIcon().find('svg');

        expect(svg.attributes('aria-hidden')).toBe('true');
        expect(svg.attributes('role')).toBeUndefined();
        expect(svg.attributes('aria-label')).toBeUndefined();
    });

    it('becomes a named image when it carries meaning', () => {
        const svg = mountIcon({ label: 'Rechercher' }).find('svg');

        expect(svg.attributes('role')).toBe('img');
        expect(svg.attributes('aria-label')).toBe('Rechercher');
        expect(svg.attributes('aria-hidden')).toBeUndefined();
    });

    it('warns about a size outside the scale', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mountIcon({ size: 'xl' });

        expect(warn.mock.calls.map(([message]) => message).join('\n'))
            .toContain('Invalid prop: custom validator check failed for prop "size"');
    });
});
