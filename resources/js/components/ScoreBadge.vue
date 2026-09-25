<template>
    <div class="relative size-24 shrink-0">
        <svg viewBox="0 0 100 100" class="size-full -rotate-90" role="img" :aria-label="summary">
            <circle cx="50" cy="50" :r="RADIUS" fill="none" stroke="var(--wp-border)" stroke-width="7" />
            <circle
                :cx="50"
                :cy="50"
                :r="RADIUS"
                fill="none"
                :stroke="color?.base ?? 'var(--wp-accent)'"
                stroke-width="7"
                stroke-linecap="round"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="offset"
                class="transition-[stroke-dashoffset] duration-slow ease-enter"
            />
        </svg>
        <div aria-hidden="true" class="absolute inset-0 flex flex-col items-center justify-center">
            <span data-score class="text-xl font-bold leading-none tabular-nums" :style="{ color: readable(color) }">{{ formatScore(score) }}</span>
            <span data-rank class="mt-1 text-xs font-semibold" :style="{ color: readable(color) }">{{ rank }}</span>
        </div>
    </div>
</template>

<script>
const RADIUS = 42;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
</script>

<script setup>
import { computed } from 'vue';
import { formatScore } from '../utils/formatScore';
import { rankColor } from '../utils/wowColors';
import { useWowColor } from '../composables/useWowColor';

const props = defineProps({
    score: { type: Number, required: true },
    rank: { type: String, required: true },
});

const { readable, safe } = useWowColor();

const color = computed(() => safe(rankColor, props.rank));
const offset = computed(() => CIRCUMFERENCE - (Math.min(Math.max(props.score, 0), 100) / 100) * CIRCUMFERENCE);
const summary = computed(() => `Score ${formatScore(props.score)} sur 100, rang ${props.rank}`);
</script>
