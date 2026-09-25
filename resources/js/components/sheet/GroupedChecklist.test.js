import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('../../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import GroupedChecklist from './GroupedChecklist.vue';

const group = (name, items) => ({ name, items, total: items.length, completed: items.filter((item) => item.is_completed).length });
const item = (id, done = false) => ({ id, name: `Quête ${id}`, is_completed: done });

const GROUPS = Array.from({ length: 10 }, (_, index) => group(`Zone ${index + 1}`, [item(index * 2, true), item(index * 2 + 1)]));

const mountList = (props = {}) => mount(GroupedChecklist, {
    props: { groups: GROUPS, title: 'Décomposition par zone', dimension: 'quests', emptyMessage: 'Aucune quête ne correspond.', ...props },
    slots: { item: '<template #item="{ item }"><span data-item>{{ item.name }}</span></template>' },
});

const headers = (wrapper) => wrapper.findAll('[data-group] > button');

describe('GroupedChecklist', () => {
    it('titles the list in a h3', () => {
        expect(mountList().find('h3').text()).toBe('Décomposition par zone');
    });

    it('shows eight groups per page, with a pagination for the rest', () => {
        const wrapper = mountList();

        expect(wrapper.findAll('[data-group]')).toHaveLength(8);
        expect(wrapper.findComponent({ name: 'Pagination' }).props()).toEqual(expect.objectContaining({ page: 1, pageCount: 2 }));
    });

    it('turns the page', async () => {
        const wrapper = mountList();

        await wrapper.findComponent({ name: 'Pagination' }).vm.$emit('update:page', 2);

        expect(wrapper.findAll('[data-group]').map((entry) => entry.find('button').text())).toEqual([
            expect.stringContaining('Zone 9'),
            expect.stringContaining('Zone 10'),
        ]);
    });

    it('gives each group its counts and a labelled progress bar', () => {
        const first = mountList().find('[data-group]');

        expect(first.text()).toContain('1 / 2');
        expect(first.find('[role="progressbar"]').attributes('aria-label')).toBe('Zone 1 : 1 sur 2');
    });

    it('expands a group from a real button, one at a time', async () => {
        const wrapper = mountList();

        await headers(wrapper)[0].trigger('click');

        expect(headers(wrapper)[0].attributes('aria-expanded')).toBe('true');
        expect(wrapper.find(`#${headers(wrapper)[0].attributes('aria-controls')}`).findAll('[data-item]').map((entry) => entry.text())).toEqual(['Quête 0', 'Quête 1']);

        await headers(wrapper)[1].trigger('click');

        expect(headers(wrapper)[0].attributes('aria-expanded')).toBe('false');
        expect(wrapper.findAll('[data-item]')).toHaveLength(2);

        await headers(wrapper)[1].trigger('click');

        expect(wrapper.findAll('[data-item]')).toHaveLength(0);
    });

    it('goes back to the first page when the groups change', async () => {
        const wrapper = mountList();
        await wrapper.findComponent({ name: 'Pagination' }).vm.$emit('update:page', 2);

        await wrapper.setProps({ groups: GROUPS.slice(0, 9) });

        expect(wrapper.findComponent({ name: 'Pagination' }).props('page')).toBe(1);
    });

    it('explains an empty list', () => {
        const wrapper = mountList({ groups: [] });

        expect(wrapper.text()).toContain('Aucune quête ne correspond.');
        expect(wrapper.find('[data-group]').exists()).toBe(false);
    });
});
