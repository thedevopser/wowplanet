<template>
    <div class="space-y-8">
        <Head>
            <title>Taxonomie des collections - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader
            title="Taxonomie des collections"
            description="Le rangement des montures, mascottes et décorations — catégorie puis source — est de la curation que ni l'API ni les DB2 ne portent. Chaque patch apporte des entrées que personne n'a encore rangées : elles sont ici."
        />

        <Card as="section" aria-label="Entrées à arbitrer" class="space-y-6 p-5 sm:p-6">
            <div role="group" aria-label="Collection" class="flex flex-wrap gap-2">
                <Button
                    v-for="(count, name) in counts"
                    :key="name"
                    :data-tab="name"
                    size="sm"
                    :variant="name === entity ? 'primary' : 'secondary'"
                    :aria-pressed="name === entity ? 'true' : 'false'"
                    @click="switchTo(name)"
                >
                    {{ LABELS[name] }}
                    <span class="font-mono text-xs tabular-nums">{{ count.pending.toLocaleString('fr-FR') }}</span>
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="term"
                    type="search"
                    aria-label="Chercher une entrée"
                    placeholder="Chercher par nom ou par identifiant"
                    class="min-h-11 min-w-48 flex-1 rounded-ui-md border border-strong bg-surface px-3 text-base text-default placeholder:text-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    @keyup.enter="runSearch"
                />
                <Button @click="runSearch">
                    Chercher
                </Button>
                <span class="text-sm tabular-nums text-muted">
                    {{ matched.toLocaleString('fr-FR') }} à arbitrer<template v-if="matched > entries.length">, {{ perPage }} affichées</template>
                </span>
            </div>

            <PendingTaxonomyTable
                :entries="entries"
                :selected="selection"
                :disabled="busy"
                @update:selected="selection = $event"
            />
        </Card>

        <Card as="section" aria-labelledby="file-heading" class="space-y-5 p-5 sm:p-6">
            <div>
                <h2 id="file-heading" class="font-display text-2xl font-semibold text-default">Ranger {{ selection.length }} entrée{{ selection.length > 1 ? 's' : '' }}</h2>
                <p class="mt-1 text-sm text-muted">
                    Les libellés sont stockés en anglais, comme le reste de la curation : les pages de collection les
                    traduisent à l'affichage.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Combobox v-model="category" data-field="category" label="Catégorie" :options="vocabulary.categories" />
                <Combobox v-model="source" data-field="source" label="Source" :options="vocabulary.sources" />
            </div>

            <p
                v-if="isNewCategory"
                data-alert="new-category"
                class="rounded-ui-md border border-warning/40 bg-warning/10 p-3 text-sm text-default"
            >
                « {{ category.trim() }} » n'existe pas encore dans cette collection : elle apparaîtra comme un nouvel
                onglet dans les pages de collection, et se rangera en fin de liste tant qu'elle n'est ni une extension
                ni une catégorie connue du front.
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary" :disabled="busy || selection.length === 0" @click="file(category, source)">
                    Ranger la sélection
                </Button>
                <Button :disabled="busy || selection.length === 0" @click="file(null, null)">
                    Ranger nulle part
                </Button>
                <span class="text-sm text-muted">
                    Ranger nulle part est un arbitrage à part entière : l'entrée cesse de revenir dans cette liste.
                </span>
            </div>

            <p v-if="filed" role="status" class="text-sm text-success">{{ filed }}</p>
            <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
        </Card>

        <TaxonomySnapshotPanel :snapshot="snapshot" />
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
import TaxonomySnapshotPanel from '../components/admin/TaxonomySnapshotPanel.vue';
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import PendingTaxonomyTable from '../components/admin/PendingTaxonomyTable.vue';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Combobox from '../components/ui/Combobox.vue';

const LABELS = {
    mount: 'Montures',
    pet: 'Mascottes',
    decor: 'Décorations',
};

const props = defineProps({
    entity: { type: String, required: true },
    search: { type: String, default: '' },
    counts: { type: Object, required: true },
    entries: { type: Array, required: true },
    matched: { type: Number, required: true },
    perPage: { type: Number, required: true },
    vocabulary: { type: Object, required: true },
    snapshot: { type: Object, required: true },
});

const selection = ref([]);
const term = ref(props.search);
const category = ref('');
const source = ref('');
const busy = ref(false);
const filed = ref('');
const error = ref('');

// Une catégorie neuve n'est pas une faute — c'est ainsi qu'une catégorie entre — mais elle
// se voit immédiatement dans les pages de collection, ce qui mérite d'être dit avant.
const isNewCategory = computed(() => {
    const typed = category.value.trim();

    return typed !== '' && ! props.vocabulary.categories.includes(typed);
});

// La liste vient du serveur : changer de collection ou chercher, c'est redemander la page.
const switchTo = name => router.visit(query({ entity: name }), { preserveScroll: true });
const runSearch = () => router.visit(query({ entity: props.entity, search: term.value }), { preserveScroll: true });

const query = params => {
    const search = new URLSearchParams(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null)
    );

    return `/admin/taxonomy?${search.toString()}`;
};

const file = async (chosenCategory, chosenSource) => {
    busy.value = true;
    filed.value = '';
    error.value = '';

    try {
        const response = await axios.post('/api/admin/taxonomy/arbitrate', {
            entity: props.entity,
            entries: selection.value,
            category: chosenCategory === null ? null : chosenCategory.trim() || null,
            source: chosenSource === null ? null : chosenSource.trim() || null,
        });

        const count = response.data.arbitrated;
        filed.value = `${count} entrée${count > 1 ? 's' : ''} rangée${count > 1 ? 's' : ''}`;
        selection.value = [];
        router.reload({ only: ['entries', 'counts', 'matched', 'vocabulary', 'snapshot'] });
    } catch (err) {
        error.value = err.response?.data?.message || "Erreur lors de l'arbitrage";
    } finally {
        busy.value = false;
    }
};
</script>
