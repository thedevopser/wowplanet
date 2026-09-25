<template>
    <div class="space-y-6 py-6 sm:py-8">
        <Head>
            <title>{{ meta.title }}</title>
            <meta name="description" :content="meta.description">
            <link rel="canonical" :href="meta.canonicalUrl">
            <meta property="og:type" :content="meta.ogType">
            <meta property="og:title" :content="meta.ogTitle">
            <meta property="og:description" :content="meta.ogDescription">
            <meta property="og:image" :content="meta.ogImage">
            <meta property="og:url" :content="meta.ogUrl">
            <meta property="og:site_name" content="WowPlanet">
            <meta property="og:locale" content="fr_FR">
        </Head>

        <DatabasePageHeader
            title="Quêtes"
            :subtitle="activeExpansionName || 'Toutes les extensions'"
            :count="total"
            count-label="quêtes"
            dimension="quests"
        />

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher une quête…"
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="searchFor"
        />

        <DatabasePagination
            placement="top"
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            label="Pages des quêtes"
            @page-change="goToPage"
        />

        <CatalogTable v-if="items.length" caption="Liste des quêtes" :columns="COLUMNS" :rows="items" :busy="busy">
            <template #cell-name="{ row }">
                <a :href="`https://www.wowhead.com/fr/quest=${row.id}`" target="_blank" rel="noopener" class="text-default hover:underline">{{ row.name_fr }}</a>
            </template>
            <template #cell-zone="{ row }"><span class="text-muted">{{ row.zone_name }}</span></template>
            <template #cell-faction="{ row }">
                <template v-if="row.faction">
                    <Badge v-if="hasFactionColor(row.faction)" tone="faction" :value="row.faction">{{ row.faction }}</Badge>
                    <Badge v-else>{{ row.faction }}</Badge>
                </template>
            </template>
        </CatalogTable>
        <EmptyState v-else :icon="SearchX" title="Aucun résultat trouvé" message="Aucune entrée ne correspond à cette recherche." />

        <DatabasePagination
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            label="Pages des quêtes"
            @page-change="goToPage"
        />
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';
import DatabaseLayout from '../layouts/DatabaseLayout.vue';

export default {
    layout: [AppLayout, DatabaseLayout],
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { SearchX } from 'lucide-vue-next';
import CatalogTable from '../components/CatalogTable.vue';
import SearchFilter from '../components/SearchFilter.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';
import Badge from '../components/ui/Badge.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import { useCatalogPaging } from '../composables/useCatalogPaging';
import { useWowColor } from '../composables/useWowColor';
import { factionColor } from '../utils/wowColors';

const COLUMNS = [
    { key: 'name', label: 'Nom' },
    { key: 'zone', label: 'Zone', hideBelow: 'sm' },
    { key: 'faction', label: 'Faction', align: 'end', hideBelow: 'sm', class: 'w-28' },
];

const props = defineProps({
    meta: { type: Object, required: true },
    expansion: { type: String, default: null },
    search: { type: String, default: null },
    items: { type: Array, default: () => [] },
    expansions: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    current_page: { type: Number, default: 1 },
    last_page: { type: Number, default: 1 },
});

const search = ref(props.search ?? '');

const activeExpansionName = computed(() => {
    const exp = props.expansions.find(e => e.slug === props.expansion);
    return exp?.name || '';
});

const dataOnly = ['items', 'expansions', 'total', 'current_page', 'last_page', 'search'];

const { busy, goToPage, searchFor } = useCatalogPaging(dataOnly, search);

const { safe } = useWowColor();
const hasFactionColor = (faction) => safe(factionColor, faction) !== null;
</script>
