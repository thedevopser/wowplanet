import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Card from './Card.vue';

const mountCard = (props = {}, slot = '<a href="/character/hyjal/arthas">Arthas</a>') => mount(Card, { props, slots: { default: slot } });

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Card', () => {
    it('is flat by default: a surface and a thin border, no shadow', () => {
        const classes = mountCard().classes();

        expect(classes).toEqual(expect.arrayContaining(['bg-surface', 'border', 'border-default', 'rounded-ui-md']));
        expect(classes.join(' ')).not.toContain('shadow');
    });

    it('rises on hover and shows the focus of what it contains when interactive', () => {
        const classes = mountCard({ variant: 'interactive' }).classes();

        expect(classes).toEqual(expect.arrayContaining(['relative', 'hover:shadow-elevation-1', 'hover:border-strong', 'has-[:focus-visible]:outline-2']));
    });

    it('is never clickable by itself: neither a role nor a tab stop of its own', () => {
        const wrapper = mountCard({ variant: 'interactive' });

        expect(wrapper.attributes('role')).toBeUndefined();
        expect(wrapper.attributes('tabindex')).toBeUndefined();
    });

    it('lets the link it contains carry the interaction', () => {
        expect(mountCard({ variant: 'interactive' }).find('a').attributes('href')).toBe('/character/hyjal/arthas');
    });

    it.each(['div', 'article', 'section', 'li'])('can render as a %s', (as) => {
        expect(mountCard({ as }).element.tagName).toBe(as.toUpperCase());
    });

    it('warns about a variant outside the list and stays flat', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mountCard({ variant: 'glass' });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "variant"');
        expect(wrapper.classes()).not.toContain('relative');
    });

    it('warns about a tag outside the list and renders a div', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mountCard({ as: 'button' });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "as"');
        expect(wrapper.element.tagName).toBe('DIV');
    });
});
