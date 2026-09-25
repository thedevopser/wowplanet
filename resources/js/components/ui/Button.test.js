import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Button from './Button.vue';

const mountButton = (props = {}, slot = 'Enregistrer') => mount(Button, { props, slots: { default: slot } });

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Button', () => {
    it('renders a plain button that does not submit a form by default', () => {
        const button = mountButton().find('button');

        expect(button.text()).toBe('Enregistrer');
        expect(button.attributes('type')).toBe('button');
    });

    it('can submit a form when asked to', () => {
        expect(mountButton({ type: 'submit' }).find('button').attributes('type')).toBe('submit');
    });

    it('renders an Inertia link when given an address', () => {
        const wrapper = mountButton({ href: '/base-de-donnees' });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.find('a').attributes('href')).toBe('/base-de-donnees');
    });

    it('renders a plain link, outside the Inertia router, for a full page load', () => {
        const wrapper = mountButton({ href: '/auth/blizzard/redirect', external: true });

        expect(wrapper.findComponent({ name: 'Link' }).exists()).toBe(false);
        expect(wrapper.find('a').attributes('href')).toBe('/auth/blizzard/redirect');
    });

    it.each(['primary', 'secondary', 'ghost', 'danger'])('styles the %s variant differently from the others', (variant) => {
        const others = ['primary', 'secondary', 'ghost', 'danger'].filter((other) => other !== variant);
        const classes = mountButton({ variant }).find('button').classes().join(' ');

        others.forEach((other) => {
            expect(classes).not.toBe(mountButton({ variant: other }).find('button').classes().join(' '));
        });
    });

    it('fills the only main action of a screen with the accent', () => {
        expect(mountButton({ variant: 'primary' }).find('button').classes()).toEqual(expect.arrayContaining(['bg-accent', 'text-on-accent']));
    });

    it.each([['sm', 'h-9'], ['md', 'h-11']])('gives the %s size a %s height', (size, height) => {
        expect(mountButton({ size }).find('button').classes()).toContain(height);
    });

    it('shows a visible focus ring for keyboard users', () => {
        expect(mountButton().find('button').classes()).toEqual(expect.arrayContaining(['focus-visible:outline-2', 'focus-visible:outline-accent']));
    });

    it('can be disabled', () => {
        expect(mountButton({ disabled: true }).find('button').attributes('disabled')).toBeDefined();
    });

    describe('while loading', () => {
        it('is disabled and announced as busy', () => {
            const button = mountButton({ loading: true }).find('button');

            expect(button.attributes('disabled')).toBeDefined();
            expect(button.attributes('aria-busy')).toBe('true');
        });

        it('keeps its label in place, only hidden, so that its width does not change', () => {
            const wrapper = mountButton({ loading: true });
            const label = wrapper.find('[data-button-label]');

            expect(label.text()).toBe('Enregistrer');
            expect(label.classes()).toContain('invisible');
            expect(wrapper.find('[data-button-spinner]').classes()).toEqual(expect.arrayContaining(['absolute', 'inset-0']));
        });

        it('shows no spinner otherwise', () => {
            const wrapper = mountButton();

            expect(wrapper.find('[data-button-spinner]').exists()).toBe(false);
            expect(wrapper.find('button').attributes('aria-busy')).toBeUndefined();
        });

        it('spins its indicator only when motion is allowed', () => {
            const svg = mountButton({ loading: true }).find('[data-button-spinner] svg');

            expect(svg.classes()).toContain('motion-safe:animate-spin');
        });
    });

    it('warns when asked to load while being a link, which cannot be disabled', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mountButton({ href: '/', loading: true });

        expect(warn).toHaveBeenCalledWith(expect.stringContaining('[Button]'));
    });

    it('warns about a size outside the scale and falls back to the medium one', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const button = mountButton({ size: 'xl' }).find('button');

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('custom validator check failed for prop "size"');
        expect(button.classes()).toContain('h-11');
    });

    it('warns about a variant outside the list', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mountButton({ variant: 'gold' });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('custom validator check failed for prop "variant"');
    });
});
