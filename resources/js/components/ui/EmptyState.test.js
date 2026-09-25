import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { Search } from 'lucide-vue-next';
import EmptyState from './EmptyState.vue';

describe('EmptyState', () => {
    it('shows an icon, a title and a message', () => {
        const wrapper = mount(EmptyState, { props: { title: 'Aucune monture', message: 'Aucune monture ne correspond à ce filtre.', icon: Search } });

        expect(wrapper.find('svg').exists()).toBe(true);
        expect(wrapper.find('p.font-semibold').text()).toBe('Aucune monture');
        expect(wrapper.text()).toContain('Aucune monture ne correspond à ce filtre.');
    });

    it('falls back on a generic icon', () => {
        expect(mount(EmptyState, { props: { title: 'Rien ici' } }).find('svg').exists()).toBe(true);
    });

    it('offers an action when given one', () => {
        const wrapper = mount(EmptyState, { props: { title: 'Aucun favori' }, slots: { action: '<button>Parcourir</button>' } });

        expect(wrapper.find('[data-empty-action] button').text()).toBe('Parcourir');
    });

    it('has no action area otherwise', () => {
        expect(mount(EmptyState, { props: { title: 'Aucun favori' } }).find('[data-empty-action]').exists()).toBe(false);
    });
});
