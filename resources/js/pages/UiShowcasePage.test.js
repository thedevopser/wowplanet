import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div data-head><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/ui', props: {} }),
}));

import { mountWithPlugins } from '../tests/helpers';
import UiShowcasePage from './UiShowcasePage.vue';
import { useToastStore } from '../stores/toasts';

const mountPage = () => mountWithPlugins(UiShowcasePage);

describe('UiShowcasePage', () => {
    it('has a single first-level heading', async () => {
        const wrapper = await mountPage();

        expect(wrapper.findAll('h1')).toHaveLength(1);
    });

    it('keeps itself out of search engines', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-head] meta[name="robots"]').attributes('content')).toBe('noindex, nofollow');
    });

    it.each(['Button', 'IconButton', 'Card', 'StatTile', 'Badge', 'SectionHeader', 'ProgressBar', 'Tabs', 'Dialog', 'DropdownMenu', 'Skeleton', 'Spinner', 'EmptyState', 'ErrorState', 'Drawer'])('shows the %s primitive', async (name) => {
        const wrapper = await mountPage();

        expect(wrapper.findComponent({ name }).exists()).toBe(true);
    });

    it('relies on the single toast stack of the layout', async () => {
        const wrapper = await mountPage();

        expect(wrapper.findComponent({ name: 'ToastStack' }).exists()).toBe(false);
    });

    it('shows every button variant, and the loading state', async () => {
        const wrapper = await mountPage();
        const buttons = wrapper.findAllComponents({ name: 'Button' });

        expect(buttons.map((button) => button.props('variant'))).toEqual(expect.arrayContaining(['primary', 'secondary', 'ghost', 'danger']));
        expect(buttons.some((button) => button.props('loading'))).toBe(true);
    });

    it('toggles the loading demo on demand', async () => {
        const wrapper = await mountPage();
        const toggle = wrapper.find('[data-demo-loading]');

        await toggle.trigger('click');

        expect(wrapper.find('[data-demo-loading]').attributes('aria-busy')).toBe('true');
    });

    it('pushes a toast of the chosen tone', async () => {
        const wrapper = await mountPage();
        const store = useToastStore(wrapper.vm.$pinia);

        await wrapper.find('[data-demo-toast="error"]').trigger('click');

        expect(store.show).toHaveBeenCalledWith(expect.objectContaining({ tone: 'error', title: 'Échec de la synchronisation' }));
    });

    it('counts the retries asked from the error state', async () => {
        const wrapper = await mountPage();

        await wrapper.findComponent({ name: 'ErrorState' }).find('button').trigger('click');

        expect(wrapper.text()).toContain('Nouvelles tentatives : 1');
    });
});
