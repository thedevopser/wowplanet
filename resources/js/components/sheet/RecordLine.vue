<template>
    <p class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm tabular-nums">
        <span v-if="played !== null" class="text-muted">{{ played }} joués</span>
        <span class="text-success">{{ won }} victoires</span>
        <span class="text-danger">{{ lost }} défaites</span>
        <span data-win-rate class="font-semibold" :class="winRate >= EVEN ? 'text-success' : 'text-muted'">{{ winRate }} %</span>
    </p>
</template>

<script setup>
const EVEN = 50;

defineProps({
    played: { type: Number, default: null },
    won: { type: Number, required: true },
    lost: { type: Number, required: true },
    winRate: { type: Number, required: true },
});
</script>
