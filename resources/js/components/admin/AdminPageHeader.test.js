import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminPageHeader from './AdminPageHeader.vue';
import { LEGACY_PALETTE } from '../../tests/helpers';

const mountHeader = (props = {}, slots = {}) => mount(AdminPageHeader, { props: { title: 'Santé', ...props }, slots });

describe('AdminPageHeader', () => {
    it('titles the page with its only h1', () => {
        const wrapper = mountHeader();

        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.find('h1').text()).toBe('Santé');
        expect(wrapper.find('h1').classes()).toContain('font-display');
    });

    it('describes the page under its title when given a description', () => {
        expect(mountHeader({ description: 'Services et volumétrie' }).text()).toContain('Services et volumétrie');
        expect(mountHeader().find('p').exists()).toBe(false);
    });

    it('lays out the actions of the page beside its title', () => {
        const wrapper = mountHeader({}, { actions: '<button type="button">Rafraîchir</button>' });

        expect(wrapper.find('[data-header-actions] button').text()).toBe('Rafraîchir');
        expect(mountHeader().find('[data-header-actions]').exists()).toBe(false);
    });

    it('draws only with the tokens of the design system', () => {
        expect(mountHeader({ description: 'x' }).html()).not.toMatch(LEGACY_PALETTE);
    });
});
