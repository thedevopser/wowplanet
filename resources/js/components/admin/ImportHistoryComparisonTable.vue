<template>
    <p v-if="stages.length === 0" data-role="nothing-shared" class="text-sm text-muted">
        Aucune entité commune aux deux imports : il n'y a rien à comparer.
    </p>

    <div v-else class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Volume de chaque entité commune aux deux imports">
        <table class="w-full text-sm">
            <caption class="sr-only">Volume de chaque entité commune aux deux imports</caption>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-muted border-b border-default">
                    <th class="py-2 pr-3 font-medium">Entité</th>
                    <th class="py-2 pr-3 font-medium text-right">Plus ancien</th>
                    <th class="py-2 pr-3 font-medium text-right">Plus récent</th>
                    <th class="py-2 font-medium text-right">Écart</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in stages" :key="entry.stage" :data-stage="entry.stage" class="border-b border-default last:border-0 align-top">
                    <td class="py-2.5 pr-3 text-default">{{ entry.label }}</td>
                    <td data-role="older" class="py-2.5 pr-3 text-right">
                        <span class="block font-mono tabular-nums text-default">{{ formatVolume(entry.older_rows) }}</span>
                        <AdminStatusBadge kind="import" :status="entry.older.status" />
                    </td>
                    <td data-role="newer" class="py-2.5 pr-3 text-right">
                        <span class="block font-mono tabular-nums text-default">{{ formatVolume(entry.newer_rows) }}</span>
                        <AdminStatusBadge kind="import" :status="entry.newer.status" />
                    </td>
                    <td class="py-2.5 text-right space-y-1">
                        <span data-role="delta" class="block font-mono tabular-nums" :class="entry.shrunk ? 'text-danger' : 'text-default'">{{ formatSigned(entry.delta) }}</span>
                        <Badge v-if="entry.shrunk" data-alert="shrunk" tone="danger">Chute de volume</Badge>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import AdminStatusBadge from './AdminStatusBadge.vue';
import { formatSigned } from '../../utils/importHistory';
import Badge from '../ui/Badge.vue';

defineProps({
    stages: { type: Array, required: true },
});

function formatVolume(rows) {
    return rows === null ? '—' : rows.toLocaleString('fr-FR');
}
</script>
