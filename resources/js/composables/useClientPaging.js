import { computed, ref, watch } from 'vue';

export function useClientPaging(items, perPage) {
    const page = ref(1);

    const pageCount = computed(() => Math.max(1, Math.ceil(items.value.length / perPage)));
    const visible = computed(() => items.value.slice((page.value - 1) * perPage, page.value * perPage));

    watch(items, () => {
        page.value = 1;
    });

    function goTo(target) {
        page.value = target;
        window.scrollTo({ top: 0 });
    }

    return { page, pageCount, visible, goTo };
}
