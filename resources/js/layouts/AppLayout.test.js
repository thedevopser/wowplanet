import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return {
        __page: page,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: { visit: vi.fn(), on: vi.fn(() => () => {}) },
    };
});

vi.mock('../composables/useTheme', () => ({ startThemeSync: vi.fn() }));
vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: { authenticated: false } })) } }));

import { __page } from '@inertiajs/vue3';
import { startThemeSync } from '../composables/useTheme';
import { useCharacterStore } from '../stores/character';
import { useTaskStore } from '../stores/tasks';
import { useToastStore } from '../stores/toasts';
import { mountWithPlugins } from '../tests/helpers';
import AppLayout from './AppLayout.vue';
import { expectNoAxeViolations } from '../tests/axe';

const stubs = {
    AppHeaderInertia: true,
    AppFooterInertia: true,
    TaskSidebarInertia: true,
    ToastStack: true,
};

const mountLayout = (options = {}) => mountWithPlugins(AppLayout, {
    stubs,
    slots: { default: '<p>Contenu de la page</p>' },
    ...options,
});

beforeEach(() => {
    __page.url = '/';
    __page.props = {};
});

describe('AppLayout', () => {
    it('renders the page content inside the shell', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.text()).toContain('Contenu de la page');
        expect(wrapper.findComponent({ name: 'AppHeaderInertia' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'AppFooterInertia' }).exists()).toBe(true);
    });

    it('offers a skip link to the main content', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.find('a[href="#main-content"]').text()).toBe('Aller au contenu principal');
        expect(wrapper.find('#main-content').exists()).toBe(true);
    });

    it('fetches the tasks on mount for a visitor already authenticated', async () => {
        const wrapper = await mountLayout({ initialState: { character: { isAuthenticated: true } } });
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        expect(taskStore.fetchTasks).toHaveBeenCalledTimes(1);
    });

    it('takes the authentication state from the shared props', async () => {
        __page.props = { auth: { isAuthenticated: true, isAdmin: false, battletag: 'Thrall#1234' } };

        const wrapper = await mountLayout();
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(store.applySharedAuth).toHaveBeenCalledWith({ isAuthenticated: true, isAdmin: false, battletag: 'Thrall#1234' });
    });

    it('updates the authentication state on every visit', async () => {
        const wrapper = await mountLayout();
        const store = useCharacterStore(wrapper.vm.$pinia);

        __page.props = { auth: { isAuthenticated: false, isAdmin: false, battletag: '' } };
        await wrapper.vm.$nextTick();

        expect(store.applySharedAuth).toHaveBeenLastCalledWith({ isAuthenticated: false, isAdmin: false, battletag: '' });
    });

    it('never asks the server for the authentication state', async () => {
        const wrapper = await mountLayout();
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(store.checkAuth).toBeUndefined();
    });

    it('starts following the theme once mounted', async () => {
        await mountLayout();

        expect(startThemeSync).toHaveBeenCalled();
    });

    it('fetches the tasks once the visitor becomes authenticated', async () => {
        const wrapper = await mountLayout();
        const store = useCharacterStore(wrapper.vm.$pinia);
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        expect(taskStore.fetchTasks).not.toHaveBeenCalled();

        store.isAuthenticated = true;
        await wrapper.vm.$nextTick();

        expect(taskStore.fetchTasks).toHaveBeenCalled();
    });

    it('does not fetch the tasks when the visitor logs out', async () => {
        const wrapper = await mountLayout({ initialState: { character: { isAuthenticated: true } } });
        const store = useCharacterStore(wrapper.vm.$pinia);
        const taskStore = useTaskStore(wrapper.vm.$pinia);
        taskStore.fetchTasks.mockClear();

        store.isAuthenticated = false;
        await wrapper.vm.$nextTick();

        expect(taskStore.fetchTasks).not.toHaveBeenCalled();
    });

    it('shows the task sidebar only to an authenticated visitor', async () => {
        const anonymous = await mountLayout();
        const authenticated = await mountLayout({ initialState: { character: { isAuthenticated: true } } });

        expect(anonymous.findComponent({ name: 'TaskSidebarInertia' }).exists()).toBe(false);
        expect(authenticated.findComponent({ name: 'TaskSidebarInertia' }).exists()).toBe(true);
    });

    it('reports a store error as an error toast', async () => {
        const wrapper = await mountLayout({ stubActions: false });
        const store = useCharacterStore(wrapper.vm.$pinia);
        const toasts = useToastStore(wrapper.vm.$pinia);

        store.error = 'Impossible de récupérer vos personnages';
        await wrapper.vm.$nextTick();

        expect(toasts.items.map((toast) => [toast.title, toast.tone])).toEqual([['Impossible de récupérer vos personnages', 'error']]);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('warns about an expired session with a way to reconnect', async () => {
        const wrapper = await mountLayout({ stubActions: false });
        const store = useCharacterStore(wrapper.vm.$pinia);
        const toasts = useToastStore(wrapper.vm.$pinia);

        store.sessionExpired = true;
        await wrapper.vm.$nextTick();

        expect(toasts.items).toEqual([expect.objectContaining({
            title: 'Votre session a expiré',
            tone: 'warning',
            action: { label: 'Se reconnecter', href: '/auth/blizzard/redirect' },
        })]);
        expect(store.sessionExpired).toBe(false);
    });

    it('keeps a single stack of notifications at the bottom of the screen', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.findComponent({ name: 'AuthRequiredBanner' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'SessionExpiredBanner' }).exists()).toBe(false);
    });

    it('drops the centered container on the database pages', async () => {
        __page.url = '/base-de-donnees/montures?page=2';

        const wrapper = await mountLayout();

        expect(wrapper.find('#main-content .max-w-7xl').exists()).toBe(false);
        expect(wrapper.find('#main-content').classes()).toContain('flex');
    });

    it('lets the database layout shrink below the width of its content', async () => {
        __page.url = '/base-de-donnees/montures';

        const wrapper = await mountLayout();

        expect(wrapper.find('#main-content > div').classes()).toContain('min-w-0');
    });

    it('keeps the centered container on the other pages', async () => {
        __page.url = '/mon-compte';

        const wrapper = await mountLayout();

        expect(wrapper.find('#main-content .max-w-7xl').exists()).toBe(true);
        expect(wrapper.find('#main-content').classes()).not.toContain('flex');
    });

    it('lets the document scroll instead of an inner region', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.classes()).not.toContain('h-screen');
        expect(wrapper.classes()).not.toContain('overflow-hidden');
        expect(wrapper.find('#main-content').classes()).not.toContain('overflow-y-auto');
    });

    it('aligns the content on the width of the header', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.find('#main-content > div').classes()).toEqual(expect.arrayContaining(['max-w-7xl', 'px-4', 'md:px-6']));
    });

    it('mounts the single toast stack', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.findComponent({ name: 'ToastStack' }).exists()).toBe(true);
    });

    it('keeps the toast stack clear of the tasks button once authenticated', async () => {
        const wrapper = await mountLayout({ initialState: { character: { isAuthenticated: true } } });

        expect(wrapper.findComponent({ name: 'ToastStack' }).attributes('offset')).toBe('above-fab');
    });

    it('shows the server flash messages as toasts', async () => {
        __page.props = { flash: { success: null, error: 'La connexion Battle.net a été interrompue.' } };

        const wrapper = await mountLayout({ stubActions: false });
        const toasts = useToastStore(wrapper.vm.$pinia);

        expect(toasts.items.map((toast) => [toast.title, toast.tone])).toEqual([['La connexion Battle.net a été interrompue.', 'error']]);
    });

    it('frames every page with the landmarks axe expects of a whole page', async () => {
        const wrapper = await mountLayout({ attachTo: document.body });

        await expectNoAxeViolations(document.body, { landmarks: true });
        wrapper.unmount();
    });
});
