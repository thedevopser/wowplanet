import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EditorialNotice from './EditorialNotice.vue';

describe('EditorialNotice', () => {
    it('sets a notice apart as a note, readable in both themes', () => {
        const wrapper = mount(EditorialNotice, { slots: { default: 'Site fan non officiel.' } });

        expect(wrapper.attributes('role')).toBe('note');
        expect(wrapper.classes()).toEqual(expect.arrayContaining(['border-warning/40', 'text-default']));
        expect(wrapper.text()).toBe('Site fan non officiel.');
    });

    it('can inform rather than warn', () => {
        const wrapper = mount(EditorialNotice, { props: { tone: 'info' }, slots: { default: 'Astuce.' } });

        expect(wrapper.classes()).toContain('border-info/40');
        expect(wrapper.classes()).not.toContain('border-warning/40');
    });
});
