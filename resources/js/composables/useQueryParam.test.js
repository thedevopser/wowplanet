import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return { __page: page, usePage: () => page, router: { replace: vi.fn() } };
});

import { __page, router } from '@inertiajs/vue3';
import { useQueryParam } from './useQueryParam';

beforeEach(() => {
    __page.url = '/character/hyjal/arthas/progression/quetes';
    router.replace.mockClear();
});

describe('useQueryParam', () => {
    it('falls back to its default without the parameter', () => {
        expect(useQueryParam('extension', 'all').value).toBe('all');
    });

    it('reads the parameter of the address', () => {
        __page.url = '/character/hyjal/arthas/progression/quetes?extension=tww';

        expect(useQueryParam('extension', 'all').value).toBe('tww');
    });

    it('follows the address', () => {
        const extension = useQueryParam('extension', 'all');

        __page.url = '/character/hyjal/arthas/progression/quetes?extension=df';

        expect(extension.value).toBe('df');
    });

    it('writes a new value into the address, replacing the history entry', () => {
        const extension = useQueryParam('extension', 'all');

        extension.value = 'tww';

        expect(router.replace).toHaveBeenCalledWith({
            url: '/character/hyjal/arthas/progression/quetes?extension=tww',
            preserveState: true,
            preserveScroll: true,
        });
    });

    it('keeps the other parameters', () => {
        __page.url = '/character/hyjal/arthas/progression/quetes?zone=durotar';
        const extension = useQueryParam('extension', 'all');

        extension.value = 'tww';

        expect(router.replace.mock.calls[0][0].url).toBe('/character/hyjal/arthas/progression/quetes?zone=durotar&extension=tww');
    });

    it('drops the parameter back at its default', () => {
        __page.url = '/character/hyjal/arthas/progression/quetes?extension=tww';
        const extension = useQueryParam('extension', 'all');

        extension.value = 'all';

        expect(router.replace.mock.calls[0][0].url).toBe('/character/hyjal/arthas/progression/quetes');
    });
});
