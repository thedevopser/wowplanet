<template>
    <div>
        <span v-if="label" :id="labelId" class="mb-1 block text-sm font-medium text-default">{{ label }}</span>
        <div
            role="progressbar"
            :aria-valuenow="current"
            aria-valuemin="0"
            :aria-valuemax="max"
            :aria-labelledby="label ? labelId : undefined"
            :aria-label="label ? undefined : ariaLabel || undefined"
            class="h-2 w-full overflow-hidden rounded-full bg-surface-raised"
        >
            <div
                data-progress-fill
                class="h-full rounded-full transition-[width] duration-base ease-enter"
                :style="{ width: `${percent}%`, backgroundColor: color || 'var(--color-accent)' }"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, useId, watchEffect } from 'vue';

const props = defineProps({
    value: { type: Number, required: true },
    max: { type: Number, default: 100, validator: (value) => value > 0 },
    label: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
    color: { type: String, default: '' },
});

const labelId = useId();

watchEffect(() => {
    if (!props.label && !props.ariaLabel) {
        console.warn('[ProgressBar] A progress bar needs a visible label or an aria-label.');
    }
    if (props.value < 0 || props.value > props.max) {
        console.warn(`[ProgressBar] Value ${props.value} lies outside 0 to ${props.max}.`);
    }
});

const current = computed(() => Math.min(Math.max(props.value, 0), Math.max(props.max, 0)));
const percent = computed(() => (props.max > 0 ? (current.value / props.max) * 100 : 0));
</script>
