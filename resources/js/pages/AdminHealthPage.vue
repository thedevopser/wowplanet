<template>
    <div class="space-y-8">
        <Head>
            <title>État de santé - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader title="État de santé" description="Volumétries, quota Blizzard, queue et jobs échoués.">
            <template #actions>
                <Button data-action="refresh" :disabled="refreshing" @click="refresh">
                    {{ refreshing ? 'Mesure en cours…' : 'Rafraîchir' }}
                </Button>
            </template>
        </AdminPageHeader>

        <div class="space-y-2">
            <p
                v-for="(anomaly, index) in anomalies"
                :key="index"
                data-alert="anomaly"
                class="rounded-ui-md border border-danger/40 bg-danger/10 p-3 text-sm text-default"
            >{{ anomaly }}</p>
            <p
                v-if="anomalies.length === 0"
                data-role="all-clear"
                class="rounded-ui-md border border-success/40 bg-success/10 p-3 text-sm text-default"
            >
                Aucune anomalie détectée.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section data-section="services" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6">
                <h2 class="mb-4 font-display text-2xl font-semibold text-default">Services</h2>
                <HealthServiceList :services="health.services" />
            </section>

            <section data-section="quota" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6">
                <h2 class="mb-4 flex items-center gap-3 font-display text-2xl font-semibold text-default">
                    Quota Blizzard
                    <AdminStatusBadge kind="health" :status="health.quota.status" />
                </h2>
                <p v-if="health.quota.status === 'unavailable'" class="font-mono text-xs text-danger break-all">
                    {{ health.quota.detail }}
                </p>
                <dl v-else class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-muted">Consommé sur l'heure glissante</dt>
                    <dd class="text-right font-mono tabular-nums text-default">{{ formatCount(health.quota.used) }}</dd>
                    <dt class="text-muted">Plafond réservé aux imports</dt>
                    <dd class="text-right font-mono tabular-nums text-default">{{ formatCount(health.quota.import_ceiling) }}</dd>
                    <dt class="text-muted">Limite appliquée</dt>
                    <dd class="text-right font-mono tabular-nums text-default">{{ formatCount(health.quota.enforced_limit) }}</dd>
                    <dt class="text-muted">Quota publié par Blizzard</dt>
                    <dd class="text-right font-mono tabular-nums text-default">{{ formatCount(health.quota.published_quota) }}</dd>
                </dl>
            </section>
        </div>

        <section data-section="queue" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6 space-y-6">
            <h2 class="flex items-center gap-3 font-display text-2xl font-semibold text-default">
                Queue
                <AdminStatusBadge kind="health" :status="queue.status" />
            </h2>

            <p v-if="interrupted" data-role="queue-interrupted" class="text-sm text-warning">Mesure de la file interrompue.</p>

            <p v-if="queue.status === 'unavailable'" class="font-mono text-xs text-danger break-all">
                {{ queue.detail }}
            </p>

            <template v-else>
                <dl class="grid grid-cols-3 gap-4 text-sm max-w-md">
                    <div v-for="count in QUEUE_COUNTS" :key="count.key">
                        <dt class="text-muted">{{ count.label }}</dt>
                        <dd :data-count="count.key" class="font-mono text-xl tabular-nums text-default">{{ queue[count.key] }}</dd>
                    </div>
                </dl>

                <p data-role="queue-summary" aria-live="polite" class="sr-only">{{ queueSummary }}</p>

                <div data-jobs="running" class="space-y-3">
                    <h3 class="text-sm font-semibold text-default">En cours</h3>
                    <QueueJobList :jobs="queue.running" :now="now" empty-text="Aucun job en cours." />
                </div>

                <div v-if="queue.waiting.length > 0" data-jobs="waiting" class="space-y-3">
                    <h3 class="text-sm font-semibold text-default">En attente</h3>
                    <QueueJobList :jobs="queue.waiting" :now="now" />
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-default">Jobs échoués</h3>
                    <FailedJobList
                        :jobs="queue.failed"
                        :disabled="busy"
                        @retry="ask('retry', $event)"
                        @forget="ask('forget', $event)"
                    />
                </div>

                <div
                    v-if="pending"
                    data-confirm="failed-job"
                    class="space-y-3 rounded-ui-md border border-warning/40 bg-warning/10 p-4"
                >
                    <p class="text-sm text-default">{{ ACTIONS[pending.action].question }}</p>
                    <div class="flex flex-wrap gap-3">
                        <Button data-action="confirm-failed-job" variant="danger" @click="confirm">
                            {{ ACTIONS[pending.action].confirm }}
                        </Button>
                        <Button data-action="cancel-failed-job" @click="pending = null">
                            Annuler
                        </Button>
                    </div>
                </div>

                <p v-if="done" role="status" class="text-sm text-success">{{ done }}</p>
                <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
            </template>
        </section>

        <section data-section="volumes" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6 space-y-4">
            <h2 class="flex items-center gap-3 font-display text-2xl font-semibold text-default">
                Volumétries
                <AdminStatusBadge kind="health" :status="health.volumes.status" />
            </h2>
            <p v-if="health.volumes.status === 'unavailable'" class="font-mono text-xs text-danger break-all">
                {{ health.volumes.detail }}
            </p>
            <HealthVolumetryTable v-else :tables="health.volumes.tables" />
        </section>

        <section data-section="errors" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6 space-y-4">
            <h2 class="font-display text-2xl font-semibold text-default">Erreurs récentes</h2>
            <p v-if="health.errors.status === 'unavailable'" class="font-mono text-xs text-danger break-all">
                {{ health.errors.detail }}
            </p>
            <ApplicationErrorList v-else :entries="health.errors.entries" />
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
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminStatusBadge from '../components/admin/AdminStatusBadge.vue';
import HealthServiceList from '../components/admin/HealthServiceList.vue';
import HealthVolumetryTable from '../components/admin/HealthVolumetryTable.vue';
import FailedJobList from '../components/admin/FailedJobList.vue';
import QueueJobList from '../components/admin/QueueJobList.vue';
import ApplicationErrorList from '../components/admin/ApplicationErrorList.vue';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import { useQueuePolling } from '../composables/useQueuePolling';

const QUEUE_COUNTS = [
    { key: 'pending', label: 'En attente' },
    { key: 'delayed', label: 'Différés' },
    { key: 'reserved', label: 'Pris par le worker' },
];

const ACTIONS = {
    retry: {
        question: 'Le job va être remis dans la queue, et le worker le rejouera à son tour.',
        confirm: 'Relancer',
        done: 'Job remis dans la queue.',
        send: uuid => axios.post(`/api/admin/failed-jobs/${uuid}/retry`),
    },
    forget: {
        question: 'Le job va être supprimé définitivement, sans être rejoué. Sa trace d\'échec disparaît avec lui.',
        confirm: 'Supprimer quand même',
        done: 'Job supprimé.',
        send: uuid => axios.delete(`/api/admin/failed-jobs/${uuid}`),
    },
};

const props = defineProps({
    health: { type: Object, required: true },
});

const { queue, interrupted, now } = useQueuePolling(() => props.health.queue);

const refreshing = ref(false);
const pending = ref(null);
const busy = ref(false);
const done = ref(null);
const error = ref(null);

// Le serveur nomme chaque anomalie : la page les rassemble en tête, sans jamais déduire
// elle-même qu'un chiffre est inquiétant.
const anomalies = computed(() => [
    ...props.health.services.map(probe => probe.issue),
    props.health.quota.issue,
    queue.value.issue,
    props.health.volumes.issue,
    props.health.errors.issue,
].filter(issue => issue));

// Seules l'arrivée et le départ d'un job sont annoncés : les durées avancent chaque
// seconde, et un lecteur d'écran les lirait sans fin.
const queueSummary = computed(() => {
    const running = queue.value.running.length;
    const waiting = queue.value.waiting.length;

    if (running === 0 && waiting === 0) {
        return 'Aucun job en cours ni en attente.';
    }

    return `${running === 0 ? 'Aucun job' : `${running} job${running > 1 ? 's' : ''}`} en cours, ${waiting === 0 ? 'aucun' : waiting} en attente.`;
});

function refresh() {
    refreshing.value = true;
    router.reload({ only: ['health'], onFinish: () => (refreshing.value = false) });
}

function ask(action, uuid) {
    done.value = null;
    error.value = null;
    pending.value = { action, uuid };
}

async function confirm() {
    const { action, uuid } = pending.value;
    pending.value = null;
    busy.value = true;

    try {
        await ACTIONS[action].send(uuid);
        done.value = ACTIONS[action].done;
        router.reload({ only: ['health'] });
    } catch (err) {
        error.value = err.response?.data?.message || 'L\'action sur le job a échoué.';
    } finally {
        busy.value = false;
    }
}

function formatCount(count) {
    return count.toLocaleString('fr-FR');
}

</script>
