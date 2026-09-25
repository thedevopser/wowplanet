<template>
    <div class="space-y-8">
        <Head>
            <title>Imports - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader title="Imports" description="Lancement et suivi des imports de données Blizzard." />

        <Card as="section" aria-label="Lancer un import" class="space-y-6 p-5 sm:p-6">
            <ImportEntityTable
                :entities="entities"
                :selection="selection"
                :disabled="tracking.isRunning.value"
                @update:selection="selection = $event"
            />

            <fieldset class="space-y-2">
                <legend class="mb-2 text-xs uppercase tracking-wide text-muted">Mode</legend>
                <label class="flex items-start gap-2.5 text-sm text-default">
                    <input v-model="mode" type="radio" value="incremental" :disabled="tracking.isRunning.value" class="mt-1 size-4 accent-accent">
                    <span>
                        <span class="font-medium">Incrémental</span>
                        <span class="block text-sm text-muted">Une entité déjà importée pour le build WoW courant est sautée. C'est le mode de tous les jours.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 text-sm text-default">
                    <input v-model="mode" type="radio" value="forced" :disabled="tracking.isRunning.value" class="mt-1 size-4 accent-accent">
                    <span>
                        <span class="font-medium">Forcé</span>
                        <span class="block text-sm text-muted">Tout est réimporté, même ce qui est à jour. À réserver à un doute sur les données : le quota Blizzard est consommé en entier.</span>
                    </span>
                </label>
            </fieldset>

            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary" :disabled="tracking.isRunning.value || selection.length === 0" @click="launch('selection')">
                    Importer la sélection
                </Button>
                <Button :disabled="tracking.isRunning.value" @click="launch('all')">
                    Importer tout
                </Button>
                <span v-if="selection.length" class="text-sm tabular-nums text-muted">
                    ~{{ selectedCost.toLocaleString('fr-FR') }} appels pour la sélection
                </span>
            </div>

            <div v-if="confirming" class="space-y-3 rounded-ui-md border border-warning/40 bg-warning/10 p-4">
                <p class="text-sm text-default">
                    Un import forcé de tout le catalogue réimporte les huit étapes sans tenir compte du build,
                    pour environ {{ totalCost.toLocaleString('fr-FR') }} appels sur le quota Blizzard de l'heure.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button variant="danger" @click="send('all')">
                        Lancer quand même
                    </Button>
                    <Button @click="confirming = false">
                        Annuler
                    </Button>
                </div>
            </div>

            <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
        </Card>

        <ImportTrackingCard :tracking="tracking" />
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
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import ImportEntityTable from '../components/admin/ImportEntityTable.vue';
import ImportTrackingCard from '../components/admin/ImportTrackingCard.vue';
import { useImportProgress } from '../composables/useImportProgress';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';

const props = defineProps({
    entities: { type: Array, required: true },
});

const tracking = useImportProgress();
const selection = ref([]);
const mode = ref('incremental');
const confirming = ref(false);
const error = ref('');

const selectedCost = computed(() => props.entities
    .filter(entity => selection.value.includes(entity.stage))
    .reduce((total, entity) => total + entity.estimated_api_calls, 0));

const totalCost = computed(() => props.entities.reduce((total, entity) => total + entity.estimated_api_calls, 0));

onMounted(() => tracking.attach());
onUnmounted(() => tracking.stop());

// Seul un import forcé sur tout se confirme : c'est le seul qui engage le quota de
// l'heure en entier. Une sélection forcée reste bornée par ce qui est coché.
const launch = scope => {
    error.value = '';

    if (scope === 'all' && mode.value === 'forced') {
        confirming.value = true;

        return;
    }

    send(scope);
};

const send = async scope => {
    confirming.value = false;

    try {
        const response = await axios.post('/api/admin/import', {
            scope,
            stages: scope === 'all' ? [] : selection.value,
            mode: mode.value,
        });

        await tracking.start(response.data.jobId);
    } catch (err) {
        error.value = err.response?.data?.message || 'Erreur lors du lancement';
    }
};
</script>
