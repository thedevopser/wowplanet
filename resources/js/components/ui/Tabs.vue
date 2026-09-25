<template>
    <TabsRoot v-model="model" :default-value="tabs[0]?.value" activation-mode="automatic">
        <TabsList
            ref="list"
            :aria-label="label"
            :class="styles.list"
            :data-overflow="variant === 'secondary' ? String(overflowing) : undefined"
            @scroll="measureOverflow"
        >
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                :class="[FOCUS_RING, styles.trigger]"
            >
                {{ tab.label }}
            </TabsTrigger>
        </TabsList>
        <TabsContent
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            :class="['mt-4', FOCUS_RING]"
        >
            <slot :name="tab.value" />
        </TabsContent>
    </TabsRoot>
</template>

<script>
export const TAB_VARIANTS = Object.freeze({
    primary: {
        // Four sections fit a 375 px screen; the bar scrolls rather than widen the page.
        list: 'flex gap-1 overflow-x-auto overflow-y-hidden border-b border-default',
        trigger: '-mb-px h-11 shrink-0 rounded-t-ui-sm border-b-2 border-transparent px-2 text-sm font-medium text-muted sm:px-4 '
            + 'transition-colors duration-fast ease-enter hover:text-default '
            + 'data-[state=active]:border-accent data-[state=active]:text-default',
    },
    secondary: {
        // Scrolls sideways on narrow screens; the right edge fades while tabs hide behind it.
        list: 'flex gap-2 overflow-x-auto pb-1 data-[overflow=true]:mask-r-from-85%',
        trigger: 'h-9 shrink-0 rounded-full bg-surface-raised px-3 text-sm font-medium text-muted '
            + 'transition-colors duration-fast ease-enter hover:text-default '
            + 'data-[state=active]:bg-accent data-[state=active]:text-on-accent',
    },
});
</script>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

const FOCUS_RING = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';

const props = defineProps({
    tabs: { type: Array, required: true },
    label: { type: String, required: true },
    variant: { type: String, default: 'primary', validator: (value) => Object.hasOwn(TAB_VARIANTS, value) },
});

const model = defineModel({ type: String });

const styles = computed(() => TAB_VARIANTS[props.variant] ?? TAB_VARIANTS.primary);

const list = useTemplateRef('list');
const overflowing = ref(false);
let resizeObserver = null;

function measureOverflow() {
    const element = list.value?.$el;
    if (!element) {
        return;
    }

    overflowing.value = element.scrollLeft + element.clientWidth < element.scrollWidth;
}

onMounted(() => {
    measureOverflow();
    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(measureOverflow);
        resizeObserver.observe(list.value.$el);
    }
});

onBeforeUnmount(() => resizeObserver?.disconnect());
</script>
