import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DatabasePagination from './DatabasePagination.vue';

const pageNumbers = wrapper => wrapper.findAll('button').slice(1, -1).map(b => b.text());

describe('DatabasePagination', () => {
    it('renders nothing when there is a single page', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 1, lastPage: 1, total: 12 } });

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('shows the result count and the current position', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 2, lastPage: 5, total: 1234 } });

        expect(wrapper.text()).toContain('page 2 / 5');
        expect(wrapper.text()).toContain((1234).toLocaleString('fr-FR'));
    });

    it('lists every page when there are seven or fewer', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 1, lastPage: 7 } });

        expect(pageNumbers(wrapper)).toEqual(['1', '2', '3', '4', '5', '6', '7']);
    });

    it('truncates after the first pages when the current page is early', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 2, lastPage: 20 } });

        expect(wrapper.text()).toContain('…');
        expect(pageNumbers(wrapper)).toEqual(['1', '2', '3', '20']);
    });

    it('truncates on both sides when the current page is in the middle', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 10, lastPage: 20 } });

        expect(wrapper.findAll('span').filter(s => s.text() === '…')).toHaveLength(2);
        expect(pageNumbers(wrapper)).toEqual(['1', '9', '10', '11', '20']);
    });

    it('truncates before the last pages when the current page is late', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 19, lastPage: 20 } });

        expect(pageNumbers(wrapper)).toEqual(['1', '18', '19', '20']);
    });

    it('emits the requested page when a number is clicked', async () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 1, lastPage: 5 } });

        await wrapper.findAll('button')[3].trigger('click');

        expect(wrapper.emitted('page-change')).toEqual([[3]]);
    });

    it('emits the previous and the next page from the arrows', async () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 3, lastPage: 5 } });
        const buttons = wrapper.findAll('button');

        await buttons[0].trigger('click');
        await buttons.at(-1).trigger('click');

        expect(wrapper.emitted('page-change')).toEqual([[2], [4]]);
    });

    it('disables the arrows on the first and the last page', () => {
        const first = mount(DatabasePagination, { props: { currentPage: 1, lastPage: 5 } });
        const last = mount(DatabasePagination, { props: { currentPage: 5, lastPage: 5 } });

        expect(first.findAll('button')[0].attributes('disabled')).toBeDefined();
        expect(first.findAll('button').at(-1).attributes('disabled')).toBeUndefined();
        expect(last.findAll('button').at(-1).attributes('disabled')).toBeDefined();
    });

    it('marks the current page', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 3, lastPage: 5 } });

        const current = wrapper.findAll('button').find(b => b.text() === '3');

        expect(current.attributes('aria-current')).toBe('page');
    });

    it('names its navigation after the results it pages through', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 1, lastPage: 5, label: 'Pages des montures' } });

        expect(wrapper.find('nav').attributes('aria-label')).toContain('Pages des montures');
    });

    it('announces the position politely', () => {
        const wrapper = mount(DatabasePagination, { props: { currentPage: 2, lastPage: 5, total: 90 } });

        expect(wrapper.find('[aria-live="polite"]').text()).toContain('page 2 / 5');
    });

    it('tells its two copies apart, and only the bottom one speaks', () => {
        const top = mount(DatabasePagination, { props: { currentPage: 2, lastPage: 5, label: 'Pages des montures', placement: 'top' } });
        const bottom = mount(DatabasePagination, { props: { currentPage: 2, lastPage: 5, label: 'Pages des montures' } });

        expect(top.find('nav').attributes('aria-label')).toBe('Pages des montures (haut)');
        expect(top.find('[aria-live]').exists()).toBe(false);
        expect(bottom.find('nav').attributes('aria-label')).toBe('Pages des montures (bas)');
        expect(bottom.find('[aria-live="polite"]').exists()).toBe(true);
    });
});
