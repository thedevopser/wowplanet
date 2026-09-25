import { vi, describe, it, expect, beforeAll } from 'vitest';
import { h } from 'vue';

// Le point d'entrée est exclu de la mesure de couverture, ce qui ne le dispense pas
// d'être testé : c'est là que vivent les branchements, et un branchement oublié ne se
// voit nulle part ailleurs.

const useAuthGuard = vi.fn();
const inertiaAppOptions = {};
let routerEvents = [];

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

vi.mock('./composables/useAuthGuard', () => ({ useAuthGuard }));


// The entry point subscribes to the router once, on import: Vitest clears mock calls
// before each test, so they are read here.
beforeAll(async () => {
    await import('./inertia');
    const { router } = await import('@inertiajs/vue3');
    routerEvents = router.on.mock.calls.map(([event]) => event);
});

function runSetup() {
    const el = document.createElement('div');
    document.body.appendChild(el);

    inertiaAppOptions.setup({
        el,
        App: { render: () => h('div') },
        props: {},
        plugin: { install: () => {} },
    });
}

describe('amorçage Inertia', () => {
    it('installe la garde de session expirée', () => {
        useAuthGuard.mockClear();

        runSetup();

        expect(useAuthGuard).toHaveBeenCalledOnce();
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
