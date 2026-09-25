<template>
    <ul v-if="jobs.length > 0" class="space-y-2">
        <li
            v-for="(entry, index) in jobs"
            :key="`${entry.label}-${entry.since}-${index}`"
            data-queued-job
            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm"
        >
            <span class="text-default">{{ entry.label }}</span>
            <span v-if="entry.account" data-role="account" class="font-mono text-muted">{{ entry.account }}</span>
            <span data-role="since" class="ml-auto font-mono tabular-nums text-muted">depuis {{ formatDuration(Math.max(0, now - entry.since)) }}</span>
        </li>
    </ul>
    <p v-else-if="emptyText" data-role="no-queued-job" class="text-sm text-muted">{{ emptyText }}</p>
</template>

<script setup>
import { formatDuration } from '../../utils/formatDuration';

defineProps({
    jobs: { type: Array, required: true },
    now: { type: Number, required: true },
    emptyText: { type: String, default: null },
});
</script>
