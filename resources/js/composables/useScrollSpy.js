import { onBeforeUnmount, onMounted, ref } from 'vue';

// The reading zone sits below the sticky header and above the lower half of the screen.
const READING_ZONE = '-80px 0px -60% 0px';

export function useScrollSpy(ids) {
    const current = ref(ids()[0] ?? '');
    let observer = null;

    onMounted(() => {
        if (typeof IntersectionObserver === 'undefined') return;

        observer = new IntersectionObserver((entries) => {
            const entering = entries.filter((entry) => entry.isIntersecting);
            if (entering.length) {
                current.value = entering.at(-1).target.id;
            }
        }, { rootMargin: READING_ZONE });

        ids()
            .map((id) => document.getElementById(id))
            .filter((element) => element !== null)
            .forEach((element) => observer.observe(element));
    });

    onBeforeUnmount(() => observer?.disconnect());

    return current;
}
