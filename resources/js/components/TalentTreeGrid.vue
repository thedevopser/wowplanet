<template>
    <div class="relative overflow-x-auto pb-4">
        <div
            class="relative mx-auto"
            :style="{ width: gridWidth + 'px', height: gridHeight + 'px' }"
        >
            <!-- SVG connections -->
            <svg
                class="absolute inset-0 pointer-events-none"
                :width="gridWidth"
                :height="gridHeight"
            >
                <line
                    v-for="(line, i) in connectionLines"
                    :key="i"
                    :x1="line.x1" :y1="line.y1"
                    :x2="line.x2" :y2="line.y2"
                    :stroke="line.active ? 'var(--wp-accent)' : 'var(--wp-border-strong)'"
                    stroke-width="2"
                    :opacity="line.active ? 0.6 : 0.3"
                />
            </svg>

            <!-- Talent nodes -->
            <div
                v-for="node in nodes"
                :key="node.id"
                class="absolute"
                :style="{
                    left: nodeX(node.x) + 'px',
                    top: nodeY(node.y) + 'px',
                }"
            >
                <TalentNode :node="node" :size="cellSize" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import TalentNode from './TalentNode.vue';

const props = defineProps({
    nodes: { type: Array, required: true },
});

const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const onResize = () => { windowWidth.value = window.innerWidth; };
onMounted(() => window.addEventListener('resize', onResize));
onUnmounted(() => window.removeEventListener('resize', onResize));

// Responsive cell size: smaller on mobile, bigger on desktop
const cellSize = computed(() => {
    if (windowWidth.value < 640) return 44;   // mobile
    if (windowWidth.value < 1024) return 52;  // tablet
    return 60;                                 // desktop
});

const CELL_SIZE = computed(() => cellSize.value);
const GAP = computed(() => windowWidth.value < 640 ? 10 : 14);
const PADDING = 12;

const colRange = computed(() => {
    if (!props.nodes.length) return { min: 0, max: 0 };
    const cols = props.nodes.map(n => n.x);
    return { min: Math.min(...cols), max: Math.max(...cols) };
});

const rowRange = computed(() => {
    if (!props.nodes.length) return { min: 0, max: 0 };
    const rows = props.nodes.map(n => n.y);
    return { min: Math.min(...rows), max: Math.max(...rows) };
});

const gridWidth = computed(() => {
    const cols = colRange.value.max - colRange.value.min + 1;
    return cols * (CELL_SIZE.value + GAP.value) - GAP.value + PADDING * 2;
});

const gridHeight = computed(() => {
    const rows = rowRange.value.max - rowRange.value.min + 1;
    return rows * (CELL_SIZE.value + GAP.value) - GAP.value + PADDING * 2;
});

const nodeX = (col) => PADDING + (col - colRange.value.min) * (CELL_SIZE.value + GAP.value);
const nodeY = (row) => PADDING + (row - rowRange.value.min) * (CELL_SIZE.value + GAP.value);

const nodeCenter = (col, row) => ({
    x: nodeX(col) + CELL_SIZE.value / 2,
    y: nodeY(row) + CELL_SIZE.value / 2,
});

const selectedNodeIds = computed(() => {
    const ids = new Set();
    for (const node of props.nodes) {
        if (node.selected_rank > 0) ids.add(node.id);
    }
    return ids;
});

const nodeMap = computed(() => {
    const map = {};
    for (const node of props.nodes) {
        map[node.id] = node;
    }
    return map;
});

const connectionLines = computed(() => {
    const lines = [];

    for (const node of props.nodes) {
        if (!node.unlocks) continue;

        for (const targetId of node.unlocks) {
            const target = nodeMap.value[targetId];
            if (!target) continue;

            const from = nodeCenter(node.x, node.y);
            const to = nodeCenter(target.x, target.y);
            const active = selectedNodeIds.value.has(node.id) && selectedNodeIds.value.has(targetId);

            lines.push({ x1: from.x, y1: from.y, x2: to.x, y2: to.y, active });
        }
    }

    return lines;
});
</script>
