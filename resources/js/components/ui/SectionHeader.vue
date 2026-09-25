<template>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <component :is="`h${headingLevel}`" :class="HEADING_CLASSES[headingLevel]">{{ title }}</component>
            <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" data-section-actions class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>

<script>
const LEVELS = Object.freeze([2, 3, 4]);
const DEFAULT_LEVEL = 2;
</script>

<script setup>
import { computed } from 'vue';

// Cinzel is reserved to h1 and h2, and never set under 20 px.
const HEADING_CLASSES = {
    2: 'font-display text-2xl font-semibold text-default',
    3: 'text-lg font-semibold text-default',
    4: 'text-base font-semibold text-default',
};

const props = defineProps({
    title: { type: String, required: true },
    level: { type: Number, default: DEFAULT_LEVEL, validator: (value) => LEVELS.includes(value) },
    description: { type: String, default: '' },
});

const headingLevel = computed(() => (LEVELS.includes(props.level) ? props.level : DEFAULT_LEVEL));
</script>
