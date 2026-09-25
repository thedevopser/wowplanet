import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Pagination from './Pagination.vue';

let wrapper;

const mountPagination = (props = {}) => {
    wrapper = mount(Pagination, { props: { page: 1, pageCount: 5, label: 'Pages des zones', ...props } });

    return wrapper;
};

const labels = () => wrapper.findAll('li').map((item) => item.text());

afterEach(() => vi.restoreAllMocks());

describe('Pagination', () => {
    it('is a named navigation', () => {
        mountPagination();

        expect(wrapper.find('nav').attributes('aria-label')).toBe('Pages des zones');
    });

    it('renders nothing for a single page', () => {
        mountPagination({ pageCount: 1 });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('lists every page when there are few', () => {
        mountPagination({ pageCount: 5 });

        expect(labels()).toEqual(['', '1', '2', '3', '4', '5', '']);
    });

    it('shortens a long list around the current page', () => {
        mountPagination({ page: 6, pageCount: 12 });

        expect(labels()).toEqual(['', '1', '…', '5', '6', '7', '…', '12', '']);
    });

    it('marks the current page', () => {
        mountPagination({ page: 3 });

        const current = wrapper.find('[aria-current="page"]');

        expect(current.text()).toBe('3');
        expect(wrapper.findAll('[aria-current]')).toHaveLength(1);
    });

    it('names every button', () => {
        mountPagination({ page: 2 });

        const names = wrapper.findAll('button').map((button) => button.attributes('aria-label'));

        expect(names).toEqual(['Page précédente', 'Page 1', 'Page 2', 'Page 3', 'Page 4', 'Page 5', 'Page suivante']);
    });

    it('offers 44 px targets', () => {
        mountPagination();

        wrapper.findAll('button').forEach((button) => expect(button.classes()).toContain('size-11'));
    });

    it('reports the chosen page', async () => {
        mountPagination({ page: 2 });

        await wrapper.find('button[aria-label="Page 4"]').trigger('click');
        await wrapper.find('button[aria-label="Page suivante"]').trigger('click');
        await wrapper.find('button[aria-label="Page précédente"]').trigger('click');

        expect(wrapper.emitted('update:page')).toEqual([[4], [3], [1]]);
    });

    it('disables the arrows at both ends', () => {
        mountPagination({ page: 1 });
        expect(wrapper.find('button[aria-label="Page précédente"]').attributes('disabled')).toBeDefined();

        mountPagination({ page: 5 });
        expect(wrapper.find('button[aria-label="Page suivante"]').attributes('disabled')).toBeDefined();
    });
});
