<template>
    <div class="space-y-6">
        <div v-for="family in FAMILIES" :key="family.key" :data-family="family.key" class="relative overflow-x-auto" tabindex="0" role="region" :aria-labelledby="`volumes-${family.key}`">
            <h3 :id="`volumes-${family.key}`" class="mb-2 text-xs uppercase tracking-wide text-muted">{{ family.label }}</h3>
            <table class="w-full text-sm" :aria-labelledby="`volumes-${family.key}`">
                <thead>
                    <tr class="text-left text-xs text-muted border-b border-default">
                        <th class="py-2 pr-3 font-medium">Table</th>
                        <th class="py-2 pr-3 font-medium text-right">Lignes</th>
                        <th class="py-2 pr-3 font-medium text-right">Actives</th>
                        <th class="py-2 pr-3 font-medium text-right">Sans icône</th>
                        <th class="py-2 font-medium"><span class="sr-only">État</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in tablesOf(family.key)"
                        :key="entry.table"
                        :data-table="entry.table"
                        class="border-b border-default last:border-0"
                    >
                        <td class="py-2 pr-3 font-mono text-xs text-default">{{ entry.table }}</td>
                        <td class="py-2 pr-3 text-right font-mono tabular-nums text-default">{{ formatCount(entry.rows) }}</td>
                        <td data-role="active" class="py-2 pr-3 text-right font-mono tabular-nums text-muted">{{ share(entry.active, entry.rows) }}</td>
                        <td data-role="without-icon" class="py-2 pr-3 text-right font-mono tabular-nums text-muted">{{ share(entry.without_icon, entry.rows) }}</td>
                        <td class="py-2 text-right">
                            <AdminStatusBadge v-if="entry.status !== 'ok'" kind="health" :status="entry.status" />
                            <span v-if="entry.issue" class="ml-2 text-xs text-danger">{{ entry.issue }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import AdminStatusBadge from './AdminStatusBadge.vue';

const FAMILIES = [
    { key: 'catalogue', label: 'Catalogue' },
    { key: 'application', label: 'Données utilisateur' },
];

const BLANK = '—';

const props = defineProps({
    tables: { type: Array, required: true },
});

function tablesOf(family) {
    return props.tables.filter(entry => entry.family === family);
}

function formatCount(count) {
    return count.toLocaleString('fr-FR');
}

function share(part, rows) {
    if (part === null || rows === 0) {
        return BLANK;
    }

    return `${Math.round((part / rows) * 100)} %`;
}
</script>
