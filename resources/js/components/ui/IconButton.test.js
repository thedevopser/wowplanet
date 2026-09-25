import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { Trash2 } from 'lucide-vue-next';
import IconButton from './IconButton.vue';

const mountIconButton = (props = {}) => mount(IconButton, { props: { icon: Trash2, label: 'Supprimer la tâche', ...props } });

afterEach(() => {
    vi.restoreAllMocks();
});

describe('IconButton', () => {
    it('is a button named by its label, the icon itself staying silent', () => {
        const wrapper = mountIconButton();
        const button = wrapper.find('button');

        expect(button.attributes('type')).toBe('button');
        expect(button.attributes('aria-label')).toBe('Supprimer la tâche');
        expect(wrapper.find('svg').attributes('aria-hidden')).toBe('true');
    });

    it('warns when it has no label, since an icon alone names nothing', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mount(IconButton, { props: { icon: Trash2 } });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('Missing required prop: "label"');
    });

    it.each(['sm', 'md', 'lg'])('keeps a 44 px target with a %s icon', (iconSize) => {
        const wrapper = mountIconButton({ iconSize });

        expect(wrapper.find('button').classes()).toContain('size-11');
    });

    it('draws the icon at the requested size', () => {
        expect(mountIconButton({ iconSize: 'sm' }).find('svg').attributes('width')).toBe('16');
    });

    it('shows a visible focus ring', () => {
        expect(mountIconButton().find('button').classes()).toContain('focus-visible:outline-2');
    });

    it('emits its click', async () => {
        const wrapper = mountIconButton();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('can be disabled', () => {
        expect(mountIconButton({ disabled: true }).find('button').attributes('disabled')).toBeDefined();
    });

    it('offers a danger variant for destructive actions', () => {
        expect(mountIconButton({ variant: 'danger' }).find('button').classes()).toContain('text-danger');
    });

    it('warns about a variant outside the list and falls back to the ghost one', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const button = mountIconButton({ variant: 'gold' }).find('button');

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "variant"');
        expect(button.classes()).toContain('text-muted');
    });
});
