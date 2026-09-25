<template>
    <div class="relative w-full" :style="{ maxWidth: `${size + 2 * LABEL_ROOM}px`, aspectRatio: `${size + 2 * LABEL_ROOM} / ${size}` }">
        <svg :viewBox="`${-LABEL_ROOM} 0 ${size + 2 * LABEL_ROOM} ${size}`" class="h-full w-full" overflow="visible" role="img" :aria-label="summary">
            <!-- Grid lines -->
            <polygon
                v-for="level in [25, 50, 75, 100]"
                :key="level"
                :points="gridPoints(level)"
                fill="none"
                :stroke="level === 100 ? 'var(--wp-border-strong)' : 'var(--wp-border)'"
                :stroke-width="level === 100 ? 1 : 0.5"
            />

            <!-- Axis lines -->
            <line
                v-for="(_, i) in axes"
                :key="'axis-' + i"
                :x1="center"
                :y1="center"
                :x2="axisPoint(i, 100).x"
                :y2="axisPoint(i, 100).y"
                stroke="var(--wp-border)"
                stroke-width="0.5"
            />

            <!-- Data polygon -->
            <polygon
                :points="dataPoints"
                fill="currentColor"
                fill-opacity="0.2"
                stroke="currentColor"
                stroke-width="2"
                stroke-linejoin="round"
                class="text-accent transition-all duration-slow"
            />

            <!-- Data points -->
            <circle
                v-for="(axis, i) in axes"
                :key="'point-' + i"
                :cx="axisPoint(i, axis.score).x"
                :cy="axisPoint(i, axis.score).y"
                r="4"
                :fill="colors[i] || 'currentColor'"
                :class="colors.length ? '' : 'text-accent'"
                class="transition-all duration-slow"
            />

            <!-- Labels -->
            <text
                v-for="(axis, i) in axes"
                :key="'label-' + i"
                :x="labelPoint(i).x"
                :y="labelPoint(i).y"
                :text-anchor="labelAnchor(i)"
                dominant-baseline="middle"
                fill="var(--wp-text-muted)"
                class="text-xs font-semibold"
            >
                {{ axis.label }}
            </text>

            <!-- Score values -->
            <text
                v-for="(axis, i) in axes"
                :key="'value-' + i"
                :x="labelPoint(i).x"
                :y="labelPoint(i).y + 14"
                :text-anchor="labelAnchor(i)"
                dominant-baseline="middle"
                fill="var(--wp-text-subtle)"
                class="text-xs tabular-nums"
            >
                {{ Math.round(axis.score) }}%
            </text>
        </svg>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    axes: { type: Array, required: true },
    size: { type: Number, default: 320 },
    colors: { type: Array, default: () => [] },
});

// Room kept on each side for the labels, which sit outside the circle.
const LABEL_ROOM = 70;

const summary = computed(() => `Radar du score : ${props.axes.map((axis) => `${axis.label} ${Math.round(axis.score)} %`).join(', ')}`);

const center = computed(() => props.size / 2);
const radius = computed(() => props.size / 2 - 45);

function axisPoint(index, value) {
    const angle = (2 * Math.PI * index) / props.axes.length - Math.PI / 2;
    const r = (value / 100) * radius.value;
    return {
        x: center.value + r * Math.cos(angle),
        y: center.value + r * Math.sin(angle),
    };
}

function labelPoint(index) {
    const angle = (2 * Math.PI * index) / props.axes.length - Math.PI / 2;
    const r = radius.value + 28;
    return {
        x: center.value + r * Math.cos(angle),
        y: center.value + r * Math.sin(angle),
    };
}

function labelAnchor(index) {
    const angle = (2 * Math.PI * index) / props.axes.length - Math.PI / 2;
    const x = Math.cos(angle);
    if (x < -0.1) return 'end';
    if (x > 0.1) return 'start';
    return 'middle';
}

function gridPoints(level) {
    return props.axes
        .map((_, i) => {
            const p = axisPoint(i, level);
            return `${p.x},${p.y}`;
        })
        .join(' ');
}

const dataPoints = computed(() =>
    props.axes
        .map((axis, i) => {
            const p = axisPoint(i, axis.score);
            return `${p.x},${p.y}`;
        })
        .join(' ')
);
</script>
