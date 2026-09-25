<template>
    <p v-if="entries.length === 0" data-role="empty-history" class="text-sm text-muted">
        Aucun import enregistré pour l'instant.
    </p>

    <div v-else class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Imports, du plus récent au plus ancien">
        <table class="w-full text-sm">
            <caption class="sr-only">Imports, du plus récent au plus ancien</caption>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-muted border-b border-default">
                    <th class="w-8 py-2 pr-3 font-medium"><span class="sr-only">Comparer</span></th>
                    <th class="py-2 pr-3 font-medium">Début</th>
                    <th class="py-2 pr-3 font-medium">Durée</th>
                    <th class="py-2 pr-3 font-medium">Lancé par</th>
                    <th class="py-2 pr-3 font-medium">Mode</th>
                    <th class="py-2 pr-3 font-medium">Entités</th>
                    <th class="py-2 font-medium">Résultat</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="entry in entries"
                    :key="entry.job_id"
                    :data-entry="entry.job_id"
                    class="border-b border-default last:border-0 align-top"
                >
                    <td class="py-2.5 pr-3">
                        <input
                            type="checkbox"
                            :checked="selected.includes(entry.job_id)"
                            :disabled="isLocked(entry.job_id)"
                            :aria-label="`Comparer l’import du ${formatDate(entry.started_at)}`"
                            class="mt-1 size-4 accent-accent disabled:opacity-50"
                            @change="toggle(entry.job_id, $event.target.checked)"
                        />
                    </td>
                    <td class="py-2.5 pr-3 whitespace-nowrap">
                        <Link :href="`/admin/history/${entry.job_id}`" class="text-accent hover:underline">
                            {{ formatDate(entry.started_at) }}
                        </Link>
                    </td>
                    <td data-role="duration" class="py-2.5 pr-3 font-mono tabular-nums text-default whitespace-nowrap">{{ duration(entry) }}</td>
                    <td class="py-2.5 pr-3 text-default">{{ triggerLabel(entry.trigger) }}</td>
                    <td class="py-2.5 pr-3 text-default">{{ MODES[entry.mode] ?? entry.mode }}</td>
                    <td class="py-2.5 pr-3 text-muted">{{ entry.stages.map(stage => stage.label).join(', ') }}</td>
                    <td class="py-2.5 space-y-1">
                        <AdminStatusBadge kind="import" :status="entry.status" />
                        <p v-if="entry.shrunk.length" data-alert="shrunk" class="text-xs text-danger">
                            Chute de volume : {{ entry.shrunk.map(stage => stage.label).join(', ') }}
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminStatusBadge from './AdminStatusBadge.vue';
import { formatDuration } from '../../utils/formatDuration';
import { formatHistoryDate, triggerLabel, MODES } from '../../utils/importHistory';

const COMPARED = 2;

const props = defineProps({
    entries: { type: Array, required: true },
    selected: { type: Array, required: true },
});

const emit = defineEmits(['update:selected']);

function isLocked(jobId) {
    return props.selected.length >= COMPARED && !props.selected.includes(jobId);
}

function toggle(jobId, checked) {
    emit('update:selected', checked
        ? [...props.selected, jobId]
        : props.selected.filter(selected => selected !== jobId));
}

function duration(entry) {
    if (!entry.finished_at) {
        return formatDuration(null);
    }

    return formatDuration(Math.round((Date.parse(entry.finished_at) - Date.parse(entry.started_at)) / 1000));
}

const formatDate = formatHistoryDate;
</script>
