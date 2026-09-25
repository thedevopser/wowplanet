import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DatabasePageHeader from './DatabasePageHeader.vue';

describe('DatabasePageHeader', () => {
    it('renders the title as the page heading', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Montures' } });

        expect(wrapper.find('h1').text()).toContain('Montures');
    });

    it('renders the subtitle and the count label', () => {
        const wrapper = mount(DatabasePageHeader, {
            props: { title: 'Montures', subtitle: 'Toutes les montures du jeu', count: 42, countLabel: 'montures' },
        });

        expect(wrapper.text()).toContain('Toutes les montures du jeu');
        expect(wrapper.text()).toContain('montures');
    });

    it('formats the count in French', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Quêtes', count: 12345 } });

        expect(wrapper.text()).toContain((12345).toLocaleString('fr-FR'));
    });

    it('shows a zero count by default', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Quêtes' } });

        expect(wrapper.text()).toContain('0');
    });

    it('edges the header with the colour of its section', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Hauts-faits', dimension: 'achievements' } });

        expect(wrapper.find('[data-section-rule]').attributes('style')).toContain('border-left-color');
    });

    it('stays neutral without a section or with an unknown one', () => {
        const none = mount(DatabasePageHeader, { props: { title: 'Hauts-faits' } });
        const unknown = mount(DatabasePageHeader, { props: { title: 'Hauts-faits', dimension: 'fuchsia' } });

        expect(none.find('[data-section-rule]').attributes('style')).toBeUndefined();
        expect(unknown.find('[data-section-rule]').attributes('style')).toBeUndefined();
    });

    it('keeps the count in tabular figures', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Quêtes', count: 12 } });

        expect(wrapper.find('[data-count]').classes()).toContain('tabular-nums');
    });
});
