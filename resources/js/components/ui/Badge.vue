<template>
    <span
        class="inline-flex items-center rounded-ui-sm border px-2 py-0.5 text-xs font-medium"
        :class="semanticClasses"
        :style="domainStyle"
    >
        <slot />
    </span>
</template>

<script>
import { classColor, factionColor, qualityColor, rankColor, standingColor } from '../../utils/wowColors';

export const BADGE_SEMANTIC_TONES = Object.freeze({
    neutral: 'border-default bg-surface-raised text-muted',
    info: 'border-info/40 bg-info/10 text-info',
    success: 'border-success/40 bg-success/10 text-success',
    warning: 'border-warning/40 bg-warning/10 text-warning',
    danger: 'border-danger/40 bg-danger/10 text-danger',
});

const DOMAIN_TONES = Object.freeze({ class: classColor, quality: qualityColor, faction: factionColor, rank: rankColor, standing: standingColor });
</script>

<script setup>
import { computed, useSlots } from 'vue';
import { colorForTheme } from '../../utils/wowColors';
import { useTheme } from '../../composables/useTheme';

const props = defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => Object.hasOwn(BADGE_SEMANTIC_TONES, value) || Object.hasOwn(DOMAIN_TONES, value),
    },
    value: { type: [String, Number], default: null },
});

if (!useSlots().default) {
    console.warn('[Badge] A badge needs a text: its colour alone says nothing.');
}

const { effective } = useTheme();

// Resolved in setup so that an unknown game value fails at once, not at render.
const domainColor = Object.hasOwn(DOMAIN_TONES, props.tone) ? DOMAIN_TONES[props.tone](props.value) : null;

const semanticClasses = computed(() => (domainColor ? '' : BADGE_SEMANTIC_TONES[props.tone] ?? BADGE_SEMANTIC_TONES.neutral));
const domainStyle = computed(() => (domainColor
    ? { color: colorForTheme(domainColor, effective.value), borderColor: domainColor.base }
    : undefined));
</script>
