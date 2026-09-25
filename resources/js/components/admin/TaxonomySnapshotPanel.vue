<template>
    <section data-role="snapshot" aria-labelledby="snapshot-heading" class="space-y-4 rounded-ui-md border border-default bg-surface p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <h2 id="snapshot-heading" class="font-display text-2xl font-semibold text-default">Instantané versé au dépôt</h2>
                <p class="font-mono text-xs text-muted break-all">{{ snapshot.path }}</p>
            </div>
            <a
                data-action="download-snapshot"
                href="/api/admin/taxonomy/snapshot"
                download
                class="inline-flex h-11 items-center justify-center gap-2 rounded-ui-md bg-accent px-4 text-base font-medium text-on-accent transition-colors duration-fast hover:bg-accent/90
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                Télécharger l'instantané
            </a>
        </div>

        <p class="text-sm text-muted">
            {{ formatCount(snapshot.entries) }} entrées dans l'instantané livré avec l'application. Après des arbitrages,
            téléchargez l'instantané à jour et versez-le au dépôt dans
            <span class="font-mono text-default">database/data/collection_taxonomy.csv</span> : c'est ce qui fait
            survivre la curation à une reconstruction de la base.
        </p>

        <p v-if="snapshot.in_step" data-role="in-step" class="text-sm text-success">
            La base et l'instantané sont en phase.
        </p>

        <div
            v-if="snapshot.missing_in_base > 0"
            data-alert="missing-in-base"
            class="space-y-3 rounded-ui-md border border-warning/40 bg-warning/10 p-3 text-sm text-default"
        >
            <p>
                {{ formatCount(snapshot.missing_in_base) }}
                {{ snapshot.missing_in_base > 1 ? 'entrées de l\'instantané manquent en base' : 'entrée de l\'instantané manque en base' }}.
                Tant qu'elles n'y sont pas, les imports rangent ces collections comme si elles n'avaient jamais été curées.
            </p>
            <Button v-if="!confirming" data-action="load-snapshot" variant="primary" :disabled="busy" @click="confirming = true">
                Charger l'instantané
            </Button>
        </div>

        <div
            v-if="confirming"
            data-confirm="load-snapshot"
            class="space-y-3 rounded-ui-md border border-warning/40 bg-warning/10 p-4"
        >
            <p class="text-sm text-default">
                Les entrées absentes de la base vont y être ajoutées depuis l'instantané. Le chargement est additif :
                rien de ce qui est déjà en base, arbitrages compris, n'est réécrit.
            </p>
            <div class="flex flex-wrap gap-3">
                <Button data-action="confirm-load-snapshot" variant="primary" @click="load">
                    Charger
                </Button>
                <Button data-action="cancel-load-snapshot" @click="confirming = false">
                    Annuler
                </Button>
            </div>
        </div>

        <p
            v-if="snapshot.missing_in_file > 0 || snapshot.differing > 0"
            data-alert="missing-in-file"
            class="rounded-ui-md border border-warning/40 bg-warning/10 p-3 text-sm text-default"
        >
            <template v-if="snapshot.missing_in_file > 0">
                {{ formatCount(snapshot.missing_in_file) }}
                {{ snapshot.missing_in_file > 1 ? 'entrées arbitrées en base ne sont pas' : 'entrée arbitrée en base n\'est pas' }}
                dans l'instantané.
            </template>
            <template v-if="snapshot.differing > 0">
                {{ formatCount(snapshot.differing) }}
                {{ snapshot.differing > 1 ? 'entrées diffèrent' : 'entrée diffère' }} entre la base et l'instantané.
            </template>
            Téléchargez l'instantané et versez-le au dépôt pour que ces arbitrages ne vivent pas que dans cette base.
        </p>

        <p v-if="loaded" role="status" class="text-sm text-success">{{ loaded }}</p>
        <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '../ui/Button.vue';

defineProps({
    snapshot: { type: Object, required: true },
});

const confirming = ref(false);
const busy = ref(false);
const loaded = ref(null);
const error = ref(null);

async function load() {
    confirming.value = false;
    busy.value = true;
    loaded.value = null;
    error.value = null;

    try {
        const response = await axios.post('/api/admin/taxonomy/load');
        const inserted = response.data.inserted;
        loaded.value = `${formatCount(inserted)} ${inserted > 1 ? 'entrées chargées' : 'entrée chargée'} depuis l'instantané.`;
        router.reload({ only: ['entries', 'counts', 'matched', 'vocabulary', 'snapshot'] });
    } catch (err) {
        error.value = err.response?.data?.message || 'Le rechargement de l\'instantané a échoué.';
    } finally {
        busy.value = false;
    }
}

function formatCount(count) {
    return count.toLocaleString('fr-FR');
}
</script>
