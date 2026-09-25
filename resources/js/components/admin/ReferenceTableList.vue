<template>
    <div class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Tables du socle de référence">
        <table class="w-full text-sm">
            <caption class="sr-only">Tables du socle de référence</caption>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-muted border-b border-default">
                    <th class="py-2 pr-3 font-medium">Table DB2</th>
                    <th class="py-2 pr-3 font-medium text-right">Lignes</th>
                    <th class="py-2 pr-3 font-medium text-right">Précédent</th>
                    <th class="py-2 pr-3 font-medium text-right">Écart</th>
                    <th class="py-2 pr-3 font-medium">Dernier chargement</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in tables" :key="entry.source" class="border-b border-default last:border-0 align-top">
                    <td class="py-2.5 pr-3 whitespace-nowrap">
                        <span class="font-medium text-default">{{ entry.source }}</span>
                        <span class="block font-mono tabular-nums text-xs text-subtle">{{ entry.table }}</span>
                        <Badge v-if="entry.is_empty" data-alert="empty" tone="danger" class="mt-1">Table vide</Badge>
                        <Badge v-if="entry.has_shrunk" data-alert="shrunk" tone="warning" class="mt-1">Table dégarnie</Badge>
                        <Badge v-if="entry.is_stale" data-alert="stale" tone="info" class="mt-1">Ancien build</Badge>
                    </td>
                    <td class="py-2.5 pr-3 text-right font-mono tabular-nums" :class="entry.is_empty ? 'text-danger' : 'text-default'">
                        {{ formatCount(entry.rows) }}
                    </td>
                    <td class="py-2.5 pr-3 text-right font-mono tabular-nums text-muted">
                        {{ entry.previous_rows === null ? '—' : formatCount(entry.previous_rows) }}
                    </td>
                    <td class="py-2.5 pr-3 text-right font-mono tabular-nums" :class="deltaClass(entry)">
                        {{ formatDelta(entry.delta) }}
                    </td>
                    <td class="py-2.5 pr-3 text-muted whitespace-nowrap">
                        <template v-if="entry.loaded_at">
                            {{ formatDate(entry.loaded_at) }}
                            <span class="block font-mono tabular-nums text-xs" :class="entry.is_stale ? 'text-info' : 'text-subtle'">{{ entry.build }}</span>
                        </template>
                        <span v-else class="text-warning">Jamais chargée</span>
                    </td>
                    <td class="py-2.5 text-right">
                        <Button data-action="sync-table" size="sm" :disabled="disabled" @click="emit('sync', entry.source)">
                            Synchroniser
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import Badge from '../ui/Badge.vue';
import Button from '../ui/Button.vue';
defineProps({
    tables: { type: Array, required: true },
    liveBuild: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['sync']);

function deltaClass(entry) {
    if (entry.has_shrunk) return 'text-warning';
    if (entry.delta > 0) return 'text-success';

    return 'text-muted';
}

function formatDelta(delta) {
    if (delta === null) return '—';
    if (delta === 0) return '=';

    return `${delta > 0 ? '+' : ''}${delta.toLocaleString('fr-FR')}`;
}

function formatCount(value) {
    return Number(value).toLocaleString('fr-FR');
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>
