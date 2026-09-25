<template>
    <p v-if="jobs.length === 0" data-role="no-failed-job" class="text-sm text-success">
        Aucun job échoué.
    </p>

    <ul v-else>
        <li v-for="entry in jobs" :key="entry.uuid" :data-job="entry.uuid" class="flex flex-wrap items-start gap-3 border-b border-default py-3 last:border-0">
            <div class="flex-1 min-w-0 space-y-1">
                <p class="text-sm text-default">
                    <span class="font-mono">{{ shortName(entry.job) }}</span>
                    <span class="ml-2 text-xs text-muted">{{ entry.queue }} · {{ entry.failed_at }}</span>
                </p>
                <p class="font-mono text-xs text-danger break-all">{{ entry.exception }}</p>
            </div>
            <div class="flex gap-2">
                <Button data-action="retry-job" size="sm" :disabled="disabled" @click="emit('retry', entry.uuid)">
                    Relancer
                </Button>
                <Button data-action="forget-job" variant="danger" size="sm" :disabled="disabled" @click="emit('forget', entry.uuid)">
                    Supprimer
                </Button>
            </div>
        </li>
    </ul>
</template>

<script setup>
import Button from '../ui/Button.vue';

defineProps({
    jobs: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['retry', 'forget']);

function shortName(className) {
    return className.split('\\').pop();
}
</script>
