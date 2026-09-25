<template>
    <component
        :is="icon"
        :size="pixels"
        :stroke-width="STROKE_WIDTH"
        :role="label ? 'img' : undefined"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : 'true'"
        focusable="false"
    />
</template>

<script>
export const ICON_SIZES = Object.freeze({ sm: 16, md: 20, lg: 24 });
</script>

<script setup>
import { computed } from 'vue';

const STROKE_WIDTH = 2;

const props = defineProps({
    icon: { type: [Object, Function], required: true },
    size: { type: String, default: 'md', validator: (value) => Object.hasOwn(ICON_SIZES, value) },
    label: { type: String, default: '' },
});

const pixels = computed(() => ICON_SIZES[props.size] ?? ICON_SIZES.md);
</script>
