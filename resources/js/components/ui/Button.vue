<template>
    <a v-if="href && external" :href="href" :class="classes">
        <slot />
    </a>
    <Link v-else-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="disabled || loading"
        :aria-busy="loading ? 'true' : undefined"
    >
        <span data-button-label class="inline-flex items-center gap-2" :class="{ invisible: loading }">
            <slot />
        </span>
        <span v-if="loading" data-button-spinner class="absolute inset-0 flex items-center justify-center">
            <Icon :icon="LoaderCircle" size="sm" class="motion-safe:animate-spin" />
        </span>
    </button>
</template>

<script>
export const BUTTON_VARIANTS = Object.freeze({
    primary: 'bg-accent text-on-accent hover:bg-accent/90',
    secondary: 'border border-strong bg-surface-raised text-default hover:bg-surface',
    ghost: 'bg-transparent text-default hover:bg-surface-raised',
    danger: 'border border-danger bg-transparent text-danger hover:bg-danger/10',
});

export const BUTTON_SIZES = Object.freeze({
    sm: 'h-9 px-3 text-sm',
    md: 'h-11 px-4 text-base',
});
</script>

<script setup>
import { computed, watchEffect } from 'vue';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import Icon from './Icon.vue';

const BASE = 'relative inline-flex items-center justify-center gap-2 rounded-ui-md font-medium '
    + 'transition-colors duration-fast ease-enter '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent '
    + 'disabled:cursor-not-allowed disabled:opacity-50';

const props = defineProps({
    variant: { type: String, default: 'secondary', validator: (value) => Object.hasOwn(BUTTON_VARIANTS, value) },
    size: { type: String, default: 'md', validator: (value) => Object.hasOwn(BUTTON_SIZES, value) },
    type: { type: String, default: 'button', validator: (value) => ['button', 'submit', 'reset'].includes(value) },
    href: { type: String, default: '' },
    // A route answered by a redirect off the site (OAuth) needs a full page load.
    external: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

watchEffect(() => {
    if (props.href && (props.loading || props.disabled)) {
        console.warn('[Button] A link cannot be disabled nor loading: render a button instead.');
    }
});

const classes = computed(() => [
    BASE,
    BUTTON_VARIANTS[props.variant] ?? BUTTON_VARIANTS.secondary,
    BUTTON_SIZES[props.size] ?? BUTTON_SIZES.md,
]);
</script>
