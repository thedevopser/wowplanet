import { describe, it, expect, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import { useClientPaging } from './useClientPaging';

const numbers = (count) => Array.from({ length: count }, (_, index) => index + 1);

describe('useClientPaging', () => {
    it('shows the first page of the list', () => {
        const { page, pageCount, visible } = useClientPaging(ref(numbers(120)), 50);

        expect(page.value).toBe(1);
        expect(pageCount.value).toBe(3);
        expect(visible.value).toEqual(numbers(50));
    });

    it('shows the rest of the list on the last page', () => {
        const { page, visible } = useClientPaging(ref(numbers(120)), 50);

        page.value = 3;

        expect(visible.value).toEqual(numbers(20).map((value) => value + 100));
    });

    it('goes back to the first page when the list changes', async () => {
        const items = ref(numbers(120));
        const { page } = useClientPaging(items, 50);

        page.value = 3;
        items.value = numbers(60);
        await nextTick();

        expect(page.value).toBe(1);
    });

    it('counts a single page for a short or empty list', () => {
        expect(useClientPaging(ref(numbers(10)), 50).pageCount.value).toBe(1);
        expect(useClientPaging(ref([]), 50).pageCount.value).toBe(1);
    });

    it('brings the reader back to the top of the page on a page change', () => {
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
        const { page, goTo } = useClientPaging(ref(numbers(120)), 50);

        goTo(2);

        expect(page.value).toBe(2);
        expect(scrollTo).toHaveBeenCalledWith({ top: 0 });
        scrollTo.mockRestore();
    });
});
