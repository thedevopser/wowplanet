<template>
    <div class="space-y-8">
        <Head>
            <title>Comparaison d'imports - Administration WowPlanet</title>
        </Head>

        <div class="space-y-2">
            <Link href="/admin/history" class="inline-flex min-h-11 items-center text-sm text-accent hover:underline">← Historique</Link>
            <AdminPageHeader title="Comparaison de deux imports" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="side in SIDES"
                :key="side.key"
                :data-side="side.key"
                class="space-y-2 rounded-ui-md border border-default bg-surface p-5 text-sm"
            >
                <p class="text-xs uppercase tracking-wide text-muted">{{ side.label }}</p>
                <Link :href="`/admin/history/${comparison[side.key].job_id}`" class="inline-flex min-h-11 items-center text-accent hover:underline">
                    {{ formatHistoryDate(comparison[side.key].started_at) }}
                </Link>
                <p class="text-muted">
                    {{ triggerLabel(comparison[side.key].trigger) }} · {{ MODES[comparison[side.key].mode] ?? comparison[side.key].mode }}
                </p>
                <AdminStatusBadge kind="import" :status="comparison[side.key].status" />
            </div>
        </div>

        <section aria-label="Volumes par entité" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6">
            <ImportHistoryComparisonTable :stages="comparison.stages" />
        </section>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';
import AdminLayout from '../layouts/AdminLayout.vue';

export default {
    layout: [AppLayout, AdminLayout],
};
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminStatusBadge from '../components/admin/AdminStatusBadge.vue';
import ImportHistoryComparisonTable from '../components/admin/ImportHistoryComparisonTable.vue';
import { formatHistoryDate, triggerLabel, MODES } from '../utils/importHistory';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';

const SIDES = [
    { key: 'older', label: 'Plus ancien' },
    { key: 'newer', label: 'Plus récent' },
];

defineProps({
    comparison: { type: Object, required: true },
});
</script>
