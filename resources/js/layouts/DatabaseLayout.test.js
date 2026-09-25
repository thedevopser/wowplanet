import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/base-de-donnees', props: {} });
    const handlers = {};

    return {
        __page: page,
        __handlers: handlers,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: {
            visit: vi.fn(),
            on: vi.fn((event, handler) => {
                handlers[event] = handler;
                return vi.fn();
            }),
        },
    };
});

import { __page, __handlers, router } from '@inertiajs/vue3';
import { mountWithPlugins } from '../tests/helpers';
import DatabaseLayout from './DatabaseLayout.vue';

const mountLayout = () => mountWithPlugins(DatabaseLayout, {
    slots: { default: '<p>Liste des montures</p>' },
});

const startVisit = (pathname, only = []) => __handlers.start({ detail: { visit: { url: { pathname }, only } } });

const sidebarNav = wrapper => wrapper.find('aside nav');

beforeEach(() => {
    __page.url = '/base-de-donnees';
    __page.props = {};
    vi.clearAllMocks();
});

describe('DatabaseLayout', () => {
    it('never grows wider than the screen with a wide table', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.classes()).toContain('min-w-0');
    });

    it('renders the page content', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.text()).toContain('Liste des montures');
    });

    it('lists every database section in the sidebar', async () => {
        const wrapper = await mountLayout();

        const labels = sidebarNav(wrapper).findAll('button').map(b => b.text());

        expect(labels.map(l => l.split('\n')[0].trim())).toEqual(
            expect.arrayContaining(['Montures', 'Hauts-faits', 'Quêtes', 'Mascottes', 'Décorations', 'Garde-robe', 'Professions'])
        );
    });

    it('offers a way back to the site', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.findAll('a').map(a => a.attributes('href'))).toContain('/');
    });

    it('highlights the section of the current page', async () => {
        __page.url = '/base-de-donnees/montures';

        const wrapper = await mountLayout();
        const mounts = sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Montures'));

        expect(mounts.attributes('data-active')).toBeDefined();
        expect(mounts.attributes('style')).toContain('border-left-color');
    });

    it('marks the page being read among the sub-categories', async () => {
        __page.url = '/base-de-donnees/montures/draconique';
        __page.props = { subCategories: { mounts: [{ slug: 'draconique', name: 'Draconique', count: 42 }] } };

        const wrapper = await mountLayout();
        const current = sidebarNav(wrapper).findAll('[aria-current="page"]');

        expect(current.map((link) => link.attributes('href'))).toEqual(['/base-de-donnees/montures/draconique']);
    });

    it('highlights the section of a sub-category page', async () => {
        __page.url = '/base-de-donnees/montures/draconique';

        const wrapper = await mountLayout();
        const mounts = sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Montures'));

        expect(mounts.attributes('data-active')).toBeDefined();
    });

    it('expands the active section on mount and leaves the others collapsed', async () => {
        __page.url = '/base-de-donnees/quetes';

        const wrapper = await mountLayout();
        const expanded = sidebarNav(wrapper).findAll('button[aria-expanded="true"]');

        expect(expanded.map((button) => button.text())).toEqual([expect.stringContaining('Quêtes')]);
        expect(sidebarNav(wrapper).findAll('button[aria-expanded="false"]')).toHaveLength(6);
    });

    it('keeps collapsed sub-categories in the page but out of the tab order', async () => {
        __page.url = '/base-de-donnees/quetes';
        __page.props = { subCategories: { mounts: [{ slug: 'draconique', name: 'Draconique', count: 42 }] } };

        const wrapper = await mountLayout();
        const mounts = sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Montures'));
        const list = wrapper.find(`#${mounts.attributes('aria-controls')}`);

        expect(list.find('a[href="/base-de-donnees/montures/draconique"]').exists()).toBe(true);
        expect(list.attributes('inert')).toBeDefined();
    });

    it('expands the newly active section when the page changes', async () => {
        const wrapper = await mountLayout();

        __page.url = '/base-de-donnees/mascottes';
        await wrapper.vm.$nextTick();

        const pets = sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Mascottes'));

        expect(pets.attributes('aria-expanded')).toBe('true');
    });

    it('navigates when another section is clicked', async () => {
        __page.url = '/base-de-donnees/montures';

        const wrapper = await mountLayout();
        await sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Quêtes')).trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/base-de-donnees/quetes');
    });

    it('collapses the active section instead of navigating to it again', async () => {
        __page.url = '/base-de-donnees/montures';

        const wrapper = await mountLayout();
        const mounts = () => sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Montures'));

        await mounts().trigger('click');

        expect(router.visit).not.toHaveBeenCalled();
        expect(mounts().attributes('aria-expanded')).toBe('false');
    });

    it('shows the sub-categories provided by the page', async () => {
        __page.url = '/base-de-donnees/montures';
        __page.props = { subCategories: { mounts: [{ slug: 'draconique', name: 'Draconique', count: 42 }] } };

        const wrapper = await mountLayout();
        const hrefs = sidebarNav(wrapper).findAll('a').map(a => a.attributes('href'));

        expect(hrefs).toContain('/base-de-donnees/montures');
        expect(hrefs).toContain('/base-de-donnees/montures/draconique');
        expect(sidebarNav(wrapper).text()).toContain('Draconique');
    });

    it('formats the section counts in French', async () => {
        __page.props = { counts: { mounts: 1234 } };

        const wrapper = await mountLayout();

        expect(sidebarNav(wrapper).text()).toContain((1234).toLocaleString('fr-FR'));
    });

    it('omits an empty count', async () => {
        __page.props = { counts: { mounts: 0 } };

        const wrapper = await mountLayout();
        const mounts = sidebarNav(wrapper).findAll('button').find(b => b.text().includes('Montures'));

        expect(mounts.find('[data-count]').exists()).toBe(false);
    });

    it('shows the mobile sub-category row only for a section that has some', async () => {
        __page.url = '/base-de-donnees/montures';
        __page.props = { subCategories: { mounts: [{ slug: 'draconique', name: 'Draconique', count: 42 }] } };

        const wrapper = await mountLayout();

        expect(wrapper.find('[data-mobile-subcategories]').exists()).toBe(true);
    });

    it('spells out every section in the mobile bar', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.findAll('[data-mobile-sections] a').map((link) => link.text())).toEqual(
            ['Montures', 'Hauts-faits', 'Quêtes', 'Mascottes', 'Décorations', 'Garde-robe', 'Professions'],
        );
    });

    it('hides the mobile sub-category row outside any section', async () => {
        __page.url = '/base-de-donnees';

        const wrapper = await mountLayout();

        expect(wrapper.find('[data-mobile-subcategories]').exists()).toBe(false);
    });

    it('shows a loader during a full navigation inside the database', async () => {
        const wrapper = await mountLayout();

        startVisit('/base-de-donnees/quetes');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-navigating][role="status"]').text()).toContain('Chargement');
    });

    it('hides the loader once the navigation finishes', async () => {
        const wrapper = await mountLayout();

        startVisit('/base-de-donnees/quetes');
        await wrapper.vm.$nextTick();

        __handlers.finish();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain('Chargement');
    });

    it('shows no loader for a partial reload such as pagination', async () => {
        const wrapper = await mountLayout();

        startVisit('/base-de-donnees/quetes', ['items']);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain('Chargement');
    });

    it('shows no loader when leaving the database', async () => {
        const wrapper = await mountLayout();

        startVisit('/mon-compte');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain('Chargement');
    });

    it('stops listening to navigation events once unmounted', async () => {
        const wrapper = await mountLayout();
        const [stopStart, stopFinish] = router.on.mock.results.map(r => r.value);

        wrapper.unmount();

        expect(stopStart).toHaveBeenCalled();
        expect(stopFinish).toHaveBeenCalled();
    });

    it('scrolls the mobile bar to the section being read', async () => {
        __page.url = '/base-de-donnees/professions';
        const scrollIntoView = vi.fn();
        const original = Element.prototype.scrollIntoView;
        Element.prototype.scrollIntoView = scrollIntoView;

        const wrapper = await mountLayout();
        await wrapper.vm.$nextTick();

        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'nearest', inline: 'center' });
        expect(scrollIntoView.mock.contexts[0].textContent).toContain('Professions');
        Element.prototype.scrollIntoView = original;
    });

    it('gives the way back to the site a full touch target', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.find('aside a[href="/"]').classes()).toContain('min-h-11');
    });
});
