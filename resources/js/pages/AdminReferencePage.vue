<template>
    <div class="space-y-8">
        <Head>
            <title>Socle de référence - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader
            title="Socle de référence"
            description="Les tables DB2 que l'API Blizzard n'expose pas — extension d'une quête, faction d'une zone, renom d'une réputation. Elles sont chargées depuis wago.tools, une fois par patch."
        />

        <Card as="section" aria-label="Tables du socle" class="space-y-6 p-5 sm:p-6">
            <p class="text-sm">
                <template v-if="liveBuild">
                    <span class="text-muted">Build servi par wago :</span>
                    <span class="font-mono text-default ml-1">{{ liveBuild }}</span>
                    <span v-if="staleCount" class="ml-3 text-info">
                        {{ staleCount }} {{ staleCount > 1 ? 'tables sont restées' : 'table est restée' }} sur un build antérieur.
                    </span>
                </template>
                <span v-else class="text-warning">
                    Le build courant n'a pas pu être lu chez wago : l'écart avec le socle n'est pas comparé.
                </span>
            </p>

            <ReferenceTableList
                :tables="tables"
                :live-build="liveBuild"
                :disabled="tracking.isRunning.value"
                @sync="syncTable"
            />

            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary" :disabled="tracking.isRunning.value" @click="syncAll">
                    Tout synchroniser
                </Button>
                <span class="text-sm text-muted">
                    Un chargement qui échoue laisse le socle précédent intact : rien n'est écrit avant que tous les
                    téléchargements soient acquis.
                </span>
            </div>

            <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
        </Card>

        <Card as="section" aria-labelledby="files-heading" class="space-y-6 p-5 sm:p-6">
            <div>
                <h2 id="files-heading" class="font-display text-2xl font-semibold text-default">Fichiers téléchargés</h2>
                <p class="mt-1 text-sm text-muted">
                    Chaque synchronisation laisse un fichier par table et par build. Ils ne sont relus par personne une
                    fois chargés : ils servent à rouvrir le fichier exact qui a produit un chargement douteux.
                </p>
                <p class="mt-3 text-sm">
                    <span class="text-muted">Occupé :</span>
                    <span class="ml-1 font-mono tabular-nums text-default">{{ formatBytes(store.totals.bytes) }}</span>
                    <span class="text-subtle ml-1">sur {{ store.totals.files }} fichier{{ store.totals.files > 1 ? 's' : '' }}</span>
                    <span v-if="store.totals.sweepable_files" class="ml-3 text-warning">
                        dont {{ formatBytes(store.totals.sweepable_bytes) }} qui ne servent plus.
                    </span>
                </p>
            </div>

            <ReferenceFileList
                :files="store.files"
                :missing="store.missing"
                :selected="selection"
                :disabled="busy"
                @update:selected="selection = $event"
                @delete="askToRemove([$event])"
            />

            <div class="flex flex-wrap items-center gap-3">
                <Button :disabled="busy || store.totals.sweepable_files === 0" @click="askToSweep">
                    Nettoyer les obsolètes
                </Button>
                <Button :disabled="busy || selection.length === 0" @click="askToRemove(selection)">
                    Supprimer la sélection
                </Button>
                <span v-if="purged" role="status" class="text-sm text-success">{{ purged }}</span>
            </div>

            <div
                v-if="pending"
                data-confirm="purge"
                class="space-y-3 rounded-ui-md border border-warning/40 bg-warning/10 p-4"
            >
                <p class="text-sm text-default">
                    {{ pending.count }} fichier{{ pending.count > 1 ? 's' : '' }} vont être supprimés du disque,
                    soit {{ formatBytes(pending.bytes) }} libérés. Les chargements correspondants quittent l'inventaire :
                    ils ne seront plus rouvrables.
                </p>
                <p v-if="pending.hitsLiveFiles" class="text-sm font-semibold text-warning">
                    La sélection contient un fichier en service — celui dont le chargement a produit ce qui est
                    actuellement dans les tables. Le supprimer ne vide aucune table, mais rend ce chargement
                    invérifiable.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button variant="danger" @click="sendPurge">
                        Supprimer quand même
                    </Button>
                    <Button @click="pending = null">
                        Annuler
                    </Button>
                </div>
            </div>

            <p v-if="purgeError" role="alert" class="text-sm text-danger">{{ purgeError }}</p>
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
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import ReferenceTableList from '../components/admin/ReferenceTableList.vue';
import ReferenceFileList from '../components/admin/ReferenceFileList.vue';
import ImportTrackingCard from '../components/admin/ImportTrackingCard.vue';
import { useImportProgress } from '../composables/useImportProgress';
import { formatBytes } from '../utils/formatBytes';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';

const props = defineProps({
    tables: { type: Array, required: true },
    store: { type: Object, required: true },
    liveBuild: { type: String, default: null },
});

const tracking = useImportProgress();
const error = ref('');
const selection = ref([]);
const pending = ref(null);
const purged = ref('');
const purgeError = ref('');

const staleCount = computed(() => props.tables.filter(entry => entry.is_stale).length);

// Le magasin partage le verrou des imports : proposer une purge pendant une
// synchronisation reviendrait à annoncer un chiffre calculé sur un état en train de changer.
const busy = computed(() => tracking.isRunning.value);

// Le même partage que côté serveur : ce qu'un balayage emporte, et rien d'autre.
const SWEEPABLE = ['obsolete', 'orphan'];

onMounted(() => tracking.attach());
onUnmounted(() => tracking.stop());

const syncAll = () => send({ scope: 'all' });
const syncTable = source => send({ scope: 'table', table: source });

// Le socle partage le verrou des imports : un refus ici vient d'un import en cours, et
// le message du serveur dit lequel et depuis quand.
const send = async payload => {
    error.value = '';

    try {
        const response = await axios.post('/api/admin/reference/sync', payload);

        await tracking.start(response.data.jobId);
    } catch (err) {
        error.value = err.response?.data?.message || 'Erreur lors du lancement de la synchronisation';
    }
};

const askToSweep = () => {
    const sweepable = props.store.files.filter(entry => SWEEPABLE.includes(entry.state));

    askConfirmation({ scope: 'obsolete' }, sweepable);
};

const askToRemove = filenames => {
    askConfirmation(
        { scope: 'selection', files: filenames },
        props.store.files.filter(entry => filenames.includes(entry.filename)),
    );
};

// Le chiffre annoncé vient des fichiers eux-mêmes et non des totaux de la page : une
// sélection à la main n'a pas de total tout prêt, et les deux chemins doivent compter pareil.
const askConfirmation = (payload, files) => {
    purged.value = '';
    purgeError.value = '';

    pending.value = {
        payload,
        count: files.length,
        bytes: files.reduce((total, entry) => total + entry.bytes, 0),
        hitsLiveFiles: files.some(entry => entry.state === 'live'),
    };
};

const sendPurge = async () => {
    const { payload } = pending.value;
    pending.value = null;

    try {
        const response = await axios.post('/api/admin/reference/purge', payload);

        purged.value = `${response.data.files} fichier${response.data.files > 1 ? 's' : ''} supprimé${response.data.files > 1 ? 's' : ''}, ${formatBytes(response.data.bytes)} libérés`;
        selection.value = [];
        router.reload({ only: ['store', 'tables'] });
    } catch (err) {
        purgeError.value = err.response?.data?.message || 'Erreur lors de la purge';
    }
};
</script>
