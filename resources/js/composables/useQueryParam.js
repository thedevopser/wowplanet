import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

// Only used to parse the relative address of the page.
const ORIGIN = 'http://wowplanet.local';

// A filter of a tab lives in the query string, so that it can be shared, without a
// history entry per change and without asking the server again.
export function useQueryParam(name, fallback) {
    const page = usePage();
    const update = useQueryParamsUpdate();

    return computed({
        get() {
            return new URL(page.url, ORIGIN).searchParams.get(name) ?? fallback;
        },
        set(value) {
            update([{ name, value, fallback }]);
        },
    });
}

// Inertia queues its client visits: two parameters written one after the other would each start
// from the same address, and the second would undo the first. They go in one visit instead.
export function useQueryParamsUpdate() {
    const page = usePage();

    return (changes) => {
        const url = new URL(page.url, ORIGIN);

        for (const { name, value, fallback } of changes) {
            if (value === fallback || value === null || value === '') {
                url.searchParams.delete(name);
            } else {
                url.searchParams.set(name, value);
            }
        }

        router.replace({ url: url.pathname + url.search, preserveState: true, preserveScroll: true });
    };
}
