<template>
    <div class="space-y-8">
        <Head>
            <title>Administration - WowPlanet</title>
        </Head>

        <AdminPageHeader title="Tableau de bord" description="Panneau de gestion WowPlanet" />

        <div class="space-y-4">
            <Deferred data="buildStatus">
                <template #fallback>
                    <Card data-role="build-status-loading" class="flex items-center gap-3 p-5 text-sm text-muted sm:p-6">
                        <Spinner size="sm" label="Vérification du build servi par les amonts" />
                        <span aria-hidden="true">Vérification du build servi par les amonts…</span>
                    </Card>
                </template>

                <BuildStatusBanner
                    :status="buildStatus"
                    :disabled="tracking.isRunning.value"
                    :checking="checking"
                    @update="launch"
                    @check="recheck"
                />
            </Deferred>

            <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>

            <ImportTrackingCard :tracking="tracking" />
        </div>

        <Card as="section" data-role="pending-taxonomy" aria-labelledby="pending-taxonomy-heading" class="space-y-3 p-5 sm:p-6">
            <h2 id="pending-taxonomy-heading" class="font-display text-2xl font-semibold text-default">Taxonomie des collections</h2>

            <template v-if="pendingTotal">
                <p class="text-sm text-muted">
                    <span class="font-mono text-2xl tabular-nums text-warning">{{ pendingTotal.toLocaleString('fr-FR') }}</span>
                    entrées attendent d'être rangées. Le rangement se dégrade patch après patch tant qu'on ne
                    les arbitre pas.
                </p>
                <ul class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <li v-for="(count, name) in pendingTaxonomy" :key="name" class="text-muted">
                        {{ LABELS[name] }}
                        <span class="ml-1 font-mono tabular-nums" :class="count.pending ? 'text-warning' : 'text-subtle'">
                            {{ count.pending.toLocaleString('fr-FR') }}
                        </span>
                    </li>
                </ul>
            </template>
            <p v-else class="text-sm text-muted">
                Tout est rangé : aucune entrée n'attend d'arbitrage.
            </p>

            <Button href="/admin/taxonomy">
                Ouvrir la taxonomie
            </Button>
        </Card>
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
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Deferred, router } from '@inertiajs/vue3';
import axios from 'axios';
import BuildStatusBanner from '../components/admin/BuildStatusBanner.vue';
import ImportTrackingCard from '../components/admin/ImportTrackingCard.vue';
import { useImportProgress } from '../composables/useImportProgress';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Spinner from '../components/ui/Spinner.vue';

const LABELS = {
    mount: 'Montures',
    pet: 'Mascottes',
    decor: 'Décorations',
};

const props = defineProps({
    pendingTaxonomy: { type: Object, required: true },
    buildStatus: { type: Object, default: null },
});

const tracking = useImportProgress();
const error = ref('');
const checking = ref(false);

onMounted(() => tracking.attach());
onUnmounted(() => tracking.stop());

const pendingTotal = computed(() => Object.values(props.pendingTaxonomy)
    .reduce((total, count) => total + count.pending, 0));

// Le mode reste incrémental : la porte de build juge désormais chaque famille contre son
// propre amont, et forcer relancerait les quinze mille appels des entités déjà à jour.
const launch = async stages => {
    error.value = '';

    try {
        const response = await axios.post('/api/admin/import', {
            scope: 'selection',
            stages: [].concat(stages),
            mode: 'incremental',
        });

        await tracking.start(response.data.jobId);
    } catch (err) {
        error.value = err.response?.data?.message || 'Erreur lors du lancement de la mise à jour';
    }
};

// Le serveur rouvre les amonts, la prop différée les resert : un seul sérialiseur, donc
// rien à recomposer côté client.
//
// L'attente ne se clôt qu'au retour du rechargement, et non à celui du POST : une
// vérification qui retrouve les mêmes builds ne change rien à l'écran, et rallumer le
// bouton trop tôt laisserait croire que le clic n'a rien fait.
const recheck = async () => {
    error.value = '';
    checking.value = true;

    try {
        await axios.post('/api/admin/build-check');
        router.reload({ only: ['buildStatus'], onFinish: () => (checking.value = false) });
    } catch (err) {
        checking.value = false;
        error.value = err.response?.data?.message || 'Erreur lors de la vérification du build';
    }
};
</script>
