<template>
    <p class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm tabular-nums">
        <span v-if="played !== null" class="text-muted">{{ $t('pvp.record.played', { count: played }) }}</span>
        <span class="text-success">{{ $t('pvp.record.won', { count: won }) }}</span>
        <span class="text-danger">{{ $t('pvp.record.lost', { count: lost }) }}</span>
        <span data-win-rate class="font-semibold" :class="winRate >= EVEN ? 'text-success' : 'text-muted'">{{ $t('common.percent', { value: winRate }) }}</span>
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
