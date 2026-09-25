<template>
    <div class="relative overflow-x-auto" tabindex="0" role="region" aria-label="Rapport par entité">
        <table class="w-full text-sm">
            <caption class="sr-only">Rapport par entité</caption>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-muted border-b border-default">
                    <th class="py-2 pr-3 font-medium">Entité</th>
                    <th class="py-2 pr-3 font-medium">Résultat</th>
                    <th class="py-2 pr-3 font-medium text-right" title="Créées / mises à jour / supprimées">Lignes</th>
                    <th class="py-2 pr-3 font-medium text-right">Appels</th>
                    <th class="py-2 pr-3 font-medium text-right">Durée</th>
                    <th class="py-2 font-medium text-right">Volume en base</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="step in steps"
                    :key="step.stage"
                    :data-stage="step.stage"
                    class="border-b border-default last:border-0 align-top"
                >
                    <td class="py-2.5 pr-3 text-default">{{ step.label }}</td>
                    <td class="py-2.5 pr-3 space-y-1">
                        <AdminStatusBadge kind="import" :status="step.status" />
                        <p v-if="step.error" class="font-mono tabular-nums text-xs text-danger break-all">{{ step.error }}</p>
                    </td>
                    <td data-role="rows" class="py-2.5 pr-3 text-right font-mono tabular-nums text-default whitespace-nowrap">
                        +{{ formatCount(step.created) }} / ~{{ formatCount(step.updated) }} / −{{ formatCount(step.deleted) }}
                    </td>
                    <td class="py-2.5 pr-3 text-right font-mono tabular-nums text-muted">{{ formatCount(step.api_calls) }}</td>
                    <td class="py-2.5 pr-3 text-right font-mono tabular-nums text-muted whitespace-nowrap">{{ formatDuration(Math.round(step.duration_ms / 1000)) }}</td>
                    <td class="py-2.5 text-right whitespace-nowrap space-y-1">
                        <span data-role="volume" class="block font-mono tabular-nums text-default">{{ step.rows_after === null ? '—' : formatCount(step.rows_after) }}</span>
                        <span v-if="step.rows_after !== null" data-role="delta" class="block text-xs" :class="step.shrunk ? 'text-danger' : 'text-muted'">
                            {{ step.previous_rows === null ? 'premier relevé' : `${formatSigned(step.delta)} depuis ${formatCount(step.previous_rows)}` }}
                        </span>
                        <Badge v-if="step.shrunk" data-alert="shrunk" tone="danger">Chute de volume</Badge>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import AdminStatusBadge from './AdminStatusBadge.vue';
import { formatDuration } from '../../utils/formatDuration';
import { formatSigned } from '../../utils/importHistory';
import Badge from '../ui/Badge.vue';

defineProps({
    steps: { type: Array, required: true },
});

function formatCount(count) {
    return count.toLocaleString('fr-FR');
}
</script>
