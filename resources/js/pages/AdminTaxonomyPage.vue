<template>
    <div class="space-y-8">
        <Head>
            <title>Taxonomie des collections - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader
            title="Taxonomie des collections"
            description="Le rangement des montures, mascottes et décorations — catégorie puis source — est de la curation que ni l'API ni les DB2 ne portent. Chaque patch apporte des entrées que personne n'a encore rangées : elles sont ici."
        />

        <Card as="section" :aria-label="curated ? 'Entrées déjà rangées' : 'Entrées à arbitrer'" class="space-y-6 p-5 sm:p-6">
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

            <div role="group" aria-label="Entrées affichées" class="inline-flex flex-wrap gap-1 rounded-ui-md border border-default bg-surface p-1">
                <Button
                    v-for="option in modes"
                    :key="option.value"
                    :data-mode="option.value"
                    size="sm"
                    :variant="option.value === mode ? 'primary' : 'ghost'"
                    :aria-pressed="option.value === mode ? 'true' : 'false'"
                    @click="switchMode(option.value)"
                >
                    {{ option.label }}
                </Button>
            </div>

            <Select
                v-if="curated"
                :model-value="category ?? ALL_CATEGORIES"
                label="Catégorie actuelle"
                :options="categoryOptions"
                @update:model-value="filterOn"
            />

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
                    {{ matched.toLocaleString('fr-FR') }} {{ curated ? 'rangées' : 'à arbitrer' }}<template v-if="matched > entries.length">, {{ perPage }} affichées</template>
                </span>
            </div>

            <CuratedTaxonomyTable
                v-if="curated"
                :entries="entries"
                :selected="selection"
                :disabled="busy"
                @update:selected="select"
            />
            <PendingTaxonomyTable
                v-else
                :entries="entries"
                :selected="selection"
                :disabled="busy"
                @update:selected="select"
            />
        </Card>

        <Card as="section" aria-labelledby="file-heading" class="space-y-5 p-5 sm:p-6">
            <div>
                <h2 id="file-heading" class="font-display text-2xl font-semibold text-default">{{ curated ? 'Réaffecter' : 'Ranger' }} {{ selection.length }} entrée{{ selection.length > 1 ? 's' : '' }}</h2>
                <p class="mt-1 text-sm text-muted">
                    Les libellés sont stockés en anglais, comme le reste de la curation : les pages de collection les
                    traduisent à l'affichage.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Combobox v-model="chosenCategory" data-field="category" label="Catégorie" :options="vocabulary.categories" />
                <Combobox v-model="chosenSource" data-field="source" label="Source" :options="vocabulary.sources" />
            </div>

            <p
                v-if="isNewCategory"
                data-alert="new-category"
                class="rounded-ui-md border border-warning/40 bg-warning/10 p-3 text-sm text-default"
            >
                « {{ chosenCategory.trim() }} » n'existe pas encore dans cette collection : elle apparaîtra comme un nouvel
                onglet dans les pages de collection, et se rangera en fin de liste tant qu'elle n'est ni une extension
                ni une catégorie connue du front.
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary" :disabled="busy || selection.length === 0" @click="file(chosenCategory, chosenSource)">
                    {{ curated ? 'Réaffecter' : 'Ranger la sélection' }}
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

const CURATED = 'curated';
const PENDING = 'pending';

// A select item cannot carry an empty value: « all categories » needs a value of its own.
const ALL_CATEGORIES = '__all__';

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
import Select from '../components/ui/Select.vue';
import CuratedTaxonomyTable from '../components/admin/CuratedTaxonomyTable.vue';

const LABELS = {
    mount: 'Montures',
    pet: 'Mascottes',
    decor: 'Décorations',
};

const props = defineProps({
    entity: { type: String, required: true },
    search: { type: String, default: '' },
    mode: { type: String, default: PENDING },
    counts: { type: Object, required: true },
    curatedCounts: { type: Object, default: () => ({}) },
    category: { type: String, default: null },
    categories: { type: Array, default: () => [] },
    entries: { type: Array, required: true },
    matched: { type: Number, required: true },
    perPage: { type: Number, required: true },
    vocabulary: { type: Object, required: true },
    snapshot: { type: Object, required: true },
});

const selection = ref([]);
const term = ref(props.search);
const chosenCategory = ref('');
const chosenSource = ref('');
const busy = ref(false);
const filed = ref('');
const error = ref('');

// Une catégorie neuve n'est pas une faute — c'est ainsi qu'une catégorie entre — mais elle
// se voit immédiatement dans les pages de collection, ce qui mérite d'être dit avant.
const isNewCategory = computed(() => {
    const typed = chosenCategory.value.trim();

    return typed !== '' && ! props.vocabulary.categories.includes(typed);
});

const curated = computed(() => props.mode === CURATED);

const modes = computed(() => [
    { value: PENDING, label: `À arbitrer (${formatCount(props.counts[props.entity]?.pending)})` },
    { value: CURATED, label: `Déjà rangées (${formatCount(props.curatedCounts[props.entity])})` },
]);

const categoryOptions = computed(() => [
    { value: ALL_CATEGORIES, label: 'Toutes les catégories' },
    ...props.categories.map(option => ({
        value: option.value,
        label: option.category ?? 'Sans catégorie',
        hint: formatCount(option.entries),
    })),
]);

const formatCount = count => (count ?? 0).toLocaleString('fr-FR');

// Une seule entrée cochée se corrige à partir de son rangement actuel ; plusieurs n'en
// partagent pas forcément un, les champs restent donc vides.
const select = ids => {
    selection.value = ids;

    if (! curated.value) {
        return;
    }

    const single = ids.length === 1 ? props.entries.find(entry => entry.id === ids[0]) : null;
    chosenCategory.value = single?.category ?? '';
    chosenSource.value = single?.source ?? '';
};

// La liste vient du serveur : changer de collection, de mode, de filtre ou chercher, c'est
// redemander la page. La sélection ne survit pas à un changement de liste.
const visit = params => {
    selection.value = [];
    router.visit(query({ entity: props.entity, mode: props.mode, ...params }), { preserveScroll: true });
};

const switchTo = name => visit({ entity: name, search: '', category: null });
const switchMode = mode => visit({ mode, search: '', category: null });
const filterOn = value => visit({ search: props.search, category: value === ALL_CATEGORIES ? null : value });
const runSearch = () => visit({ search: term.value, category: props.category });

const query = ({ mode, ...params }) => {
    params.mode = mode === CURATED ? CURATED : null;
    const ordered = { entity: params.entity, mode: params.mode, search: params.search, category: params.category };

    const search = new URLSearchParams(
        Object.entries(ordered).filter(([, value]) => value !== '' && value !== null && value !== undefined)
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
        const plural = count > 1 ? 's' : '';
        filed.value = `${count} entrée${plural} ${curated.value ? 'réaffectée' : 'rangée'}${plural}`;
        selection.value = [];
        router.reload({ only: ['entries', 'counts', 'curatedCounts', 'matched', 'categories', 'vocabulary', 'snapshot'] });
    } catch (err) {
        error.value = err.response?.data?.message || "Erreur lors de l'arbitrage";
    } finally {
        busy.value = false;
    }
};
</script>
