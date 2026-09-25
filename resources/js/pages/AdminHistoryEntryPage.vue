<template>
    <div class="space-y-8">
        <Head>
            <title>Rapport d'import - Administration WowPlanet</title>
        </Head>

        <div class="space-y-2">
            <Link data-action="back" href="/admin/history" class="inline-flex min-h-11 items-center text-sm text-accent hover:underline">← Historique</Link>
            <AdminPageHeader :title="`Import du ${formatHistoryDate(entry.started_at)}`" />
        </div>

        <dl data-role="summary" class="grid gap-4 rounded-ui-md border border-default bg-surface p-5 text-sm sm:grid-cols-5 sm:p-6">
            <div>
                <dt class="text-muted">Résultat</dt>
                <dd class="mt-1"><AdminStatusBadge kind="import" :status="entry.status" /></dd>
            </div>
            <div>
                <dt class="text-muted">Lancé par</dt>
                <dd class="mt-1 text-default">{{ triggerLabel(entry.trigger) }}</dd>
            </div>
            <div>
                <dt class="text-muted">Mode</dt>
                <dd class="mt-1 text-default">{{ MODES[entry.mode] ?? entry.mode }}</dd>
            </div>
            <div>
                <dt class="text-muted">Durée</dt>
                <dd class="mt-1 font-mono tabular-nums text-default">{{ duration }}</dd>
            </div>
            <div>
                <dt class="text-muted">Quota consommé</dt>
                <dd class="mt-1 font-mono tabular-nums text-default">{{ entry.budget_used.toLocaleString('fr-FR') }} appels</dd>
            </div>
        </dl>

        <section class="space-y-4 rounded-ui-md border border-default bg-surface p-5 sm:p-6">
            <h2 class="font-display text-2xl font-semibold text-default">Rapport par entité</h2>
            <ImportHistoryStepTable :steps="entry.steps" />
        </section>

        <section class="space-y-4 rounded-ui-md border border-default bg-surface p-5 sm:p-6">
            <h2 class="font-display text-2xl font-semibold text-default">Journal</h2>
            <pre
                v-if="entry.journal"
                data-role="journal"
                class="max-h-96 overflow-y-auto whitespace-pre-wrap rounded-ui-md border border-default bg-background p-4 font-mono text-xs text-default"
            >{{ entry.journal.join('\n') }}</pre>
            <p v-else data-role="journal-expired" class="text-sm text-muted">
                Le journal détaillé a expiré : il n'est gardé qu'une journée. Le rapport ci-dessus, lui, est conservé.
            </p>
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
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminStatusBadge from '../components/admin/AdminStatusBadge.vue';
import ImportHistoryStepTable from '../components/admin/ImportHistoryStepTable.vue';
import { formatDuration } from '../utils/formatDuration';
import { formatHistoryDate, triggerLabel, MODES } from '../utils/importHistory';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const duration = computed(() => (props.entry.finished_at
    ? formatDuration(Math.round((Date.parse(props.entry.finished_at) - Date.parse(props.entry.started_at)) / 1000))
    : 'non clôturé'));
</script>
