<template>
    <header
        data-section-rule
        class="flex flex-wrap items-end justify-between gap-4 rounded-ui-md border border-l-4 border-default bg-surface p-5 sm:p-6"
        :style="color ? { borderLeftColor: color.base } : undefined"
    >
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-bold text-default sm:text-3xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-muted sm:text-base">{{ subtitle }}</p>
        </div>
        <p class="text-right">
            <span data-count class="block text-2xl font-bold tabular-nums text-default sm:text-3xl">{{ count.toLocaleString('fr-FR') }}</span>
            <span class="text-sm text-muted">{{ countLabel }}</span>
        </p>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { dimensionColor, UnknownWowColorError } from '../utils/wowColors';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    count: { type: Number, default: 0 },
    countLabel: { type: String, default: '' },
    dimension: { type: String, default: '' },
});

const color = computed(() => {
    if (!props.dimension) return null;
    try {
        return dimensionColor(props.dimension);
    } catch (error) {
        if (error instanceof UnknownWowColorError) return null;
        throw error;
    }
});
</script>
