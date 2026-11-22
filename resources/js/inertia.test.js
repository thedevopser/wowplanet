import { vi, describe, it, expect, beforeAll } from 'vitest';
import { h } from 'vue';

// Le point d'entrée est exclu de la mesure de couverture, ce qui ne le dispense pas
// d'être testé : c'est là que vivent les branchements, et un branchement oublié ne se
// voit nulle part ailleurs.

const useAuthGuard = vi.fn();
const i18n = vi.hoisted(() => ({ active: 'fr', loadLanguageAsync: vi.fn() }));
const inertiaAppOptions = {};
let routerEvents = [];
let onNavigate;

vi.mock('@inertiajs/vue3', () => ({
    createInertiaApp: (options) => {
        Object.assign(inertiaAppOptions, options);
    },
    router: { on: vi.fn() },
}));

vi.mock('laravel-vite-plugin/inertia-helpers', () => ({
    resolvePageComponent: vi.fn(),
}));

vi.mock('./bootstrap', () => ({}));

vi.mock('laravel-vue-i18n', async (importOriginal) => ({
    ...(await importOriginal()),
    getActiveLanguage: () => i18n.active,
    loadLanguageAsync: i18n.loadLanguageAsync,
}));

vi.mock('./composables/useAuthGuard', () => ({ useAuthGuard }));


// The entry point subscribes to the router once, on import: Vitest clears mock calls
// before each test, so they are read here.
beforeAll(async () => {
    await import('./inertia');
    const { router } = await import('@inertiajs/vue3');
    routerEvents = router.on.mock.calls.map(([event]) => event);
    onNavigate = router.on.mock.calls[0][1];
});

function runSetup(locale = 'fr') {
    const el = document.createElement('div');
    document.body.appendChild(el);

    inertiaAppOptions.setup({
        el,
        App: { render: () => h('div', { class: 'mounted-app' }) },
        props: { initialPage: { props: { locale } } },
        plugin: { install: () => {} },
    });

    return el;
}

function navigateTo(locale) {
    onNavigate({ detail: { page: { props: { locale } } } });
}

describe('amorçage Inertia', () => {
    it('installe la garde de session expirée', () => {
        useAuthGuard.mockClear();

        runSetup();

        expect(useAuthGuard).toHaveBeenCalledOnce();
    });

    it('mounts the application once the language of the page is loaded', async () => {
        const el = runSetup('en');

        await vi.waitFor(() => expect(el.querySelector('.mounted-app')).not.toBeNull());
    });

    it('signals every visit with the progress bar, in the accent colour', () => {
        expect(inertiaAppOptions.progress).toEqual({ color: 'var(--wp-accent)', delay: 150, showSpinner: false });
    });
});

describe('navigation', () => {
    it('lays no overlay over the page during a visit', () => {
        expect(routerEvents).toEqual(['navigate']);
    });
});

describe('changement de langue', () => {
    it('loads the language of a page reached in the other language', () => {
        i18n.active = 'fr';

        navigateTo('en');

        expect(i18n.loadLanguageAsync).toHaveBeenCalledWith('en');
    });

    it('loads nothing when the language stays the same', () => {
        i18n.active = 'fr';
        i18n.loadLanguageAsync.mockClear();

        navigateTo('fr');

        expect(i18n.loadLanguageAsync).not.toHaveBeenCalled();
    });
});
