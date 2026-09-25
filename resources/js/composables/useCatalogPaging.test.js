import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/base-de-donnees/quetes/legion?page=3' }),
    router: { get: vi.fn() },
}));

import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useCatalogPaging } from './useCatalogPaging';

const ONLY = ['items', 'total'];

beforeEach(() => router.get.mockClear());

const lastOptions = () => router.get.mock.calls.at(-1)[2];

describe('useCatalogPaging', () => {
    it('reloads only the listed props for another page, keeping the search', () => {
        const { goToPage } = useCatalogPaging(ONLY, ref('dragon'));

        goToPage(4);

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/quetes/legion',
            { page: 4, search: 'dragon' },
            expect.objectContaining({ preserveState: true, only: ONLY }),
        );
    });

    it('starts a new search from the first page, without jumping', () => {
        const { searchFor } = useCatalogPaging(ONLY, ref(''));

        searchFor('');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/quetes/legion',
            { page: 1, search: undefined },
            expect.objectContaining({ preserveState: true, preserveScroll: true, only: ONLY }),
        );
    });

    it('is busy from the start of a reload until its end, even a failed one', () => {
        const { busy, goToPage } = useCatalogPaging(ONLY, ref(''));

        goToPage(2);
        expect(busy.value).toBe(false);

        lastOptions().onStart();
        expect(busy.value).toBe(true);

        lastOptions().onFinish();
        expect(busy.value).toBe(false);
    });

    it('carries the extra filters of the page in every reload', () => {
        const { goToPage, searchFor } = useCatalogPaging(ONLY, ref('elixir'), () => ({ expansion: 'legion' }));

        goToPage(2);
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ expansion: 'legion', page: 2, search: 'elixir' });

        searchFor('potion');
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ expansion: 'legion', page: 1, search: 'potion' });
    });

    it('reloads with filters of its own, from the first page', () => {
        const { reloadWith } = useCatalogPaging(ONLY, ref('elixir'), () => ({ expansion: 'legion' }));

        reloadWith({ expansion: undefined });

        expect(router.get.mock.calls.at(-1)[1]).toEqual({ expansion: undefined, page: 1, search: 'elixir' });
    });
});
