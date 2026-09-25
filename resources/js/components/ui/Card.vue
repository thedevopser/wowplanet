<template>
    <component :is="tag" :class="classes">
        <slot />
    </component>
</template>

<script>
export const CARD_VARIANTS = Object.freeze({
    flat: '',
    // The card never takes clicks itself: a link or button inside it can stretch over it
    // (after:absolute after:inset-0), and the card shows that element's keyboard focus.
    interactive: 'relative transition-shadow duration-fast ease-enter hover:border-strong hover:shadow-elevation-1 '
        + 'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-accent',
});

export const CARD_TAGS = Object.freeze(['div', 'article', 'section', 'li']);
</script>

<script setup>
import { computed } from 'vue';

const BASE = 'rounded-ui-md border border-default bg-surface';

const props = defineProps({
    variant: { type: String, default: 'flat', validator: (value) => Object.hasOwn(CARD_VARIANTS, value) },
    as: { type: String, default: 'div', validator: (value) => CARD_TAGS.includes(value) },
});

const tag = computed(() => (CARD_TAGS.includes(props.as) ? props.as : 'div'));
const classes = computed(() => [BASE, CARD_VARIANTS[props.variant] ?? CARD_VARIANTS.flat]);
</script>
