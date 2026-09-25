import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export function useCatalogPaging(only, search, filters = () => ({})) {
    const page = usePage();
    const busy = ref(false);

    const basePath = () => page.url.split('?')[0];

    function reload(params, options = {}) {
        router.get(basePath(), params, {
            preserveState: true,
            only,
            ...options,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        });
    }

    const params = (overrides) => ({ ...filters(), page: 1, search: search.value || undefined, ...overrides });

    const goToPage = (target) => reload(params({ page: target }));
    const searchFor = (value) => reload(params({ search: value || undefined }), { preserveScroll: true });
    const reloadWith = (changedFilters) => reload(params(changedFilters));

    return { busy, goToPage, searchFor, reloadWith };
}
