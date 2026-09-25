<template>
    <button
        type="button"
        :class="classes"
        :aria-label="label"
        :disabled="disabled"
        @click="emit('click', $event)"
    >
        <Icon :icon="icon" :size="iconSize" />
    </button>
</template>

<script>
export const ICON_BUTTON_VARIANTS = Object.freeze({
    ghost: 'bg-transparent text-muted hover:bg-surface-raised hover:text-default',
    secondary: 'border border-strong bg-surface-raised text-default hover:bg-surface',
    danger: 'bg-transparent text-danger hover:bg-danger/10',
});
</script>

<script setup>
import { computed } from 'vue';
import Icon, { ICON_SIZES } from './Icon.vue';

// size-11 keeps the 44 px touch target whatever the size of the icon.
const BASE = 'inline-flex size-11 shrink-0 items-center justify-center rounded-ui-md '
    + 'transition-colors duration-fast ease-enter '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent '
    + 'disabled:cursor-not-allowed disabled:opacity-50';

const props = defineProps({
    icon: { type: [Object, Function], required: true },
    label: { type: String, required: true },
    iconSize: { type: String, default: 'md', validator: (value) => Object.hasOwn(ICON_SIZES, value) },
    variant: { type: String, default: 'ghost', validator: (value) => Object.hasOwn(ICON_BUTTON_VARIANTS, value) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['click']);

const classes = computed(() => [BASE, ICON_BUTTON_VARIANTS[props.variant] ?? ICON_BUTTON_VARIANTS.ghost]);
</script>
