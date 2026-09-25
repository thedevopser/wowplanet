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
            title="Hauts-faits"
            :subtitle="activeExpansionName || 'Toutes les extensions'"
            :count="total"
            count-label="hauts-faits"
            dimension="achievements"
        />

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher un haut-fait…"
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="searchFor"
        />

        <DatabasePagination
            placement="top"
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            label="Pages des hauts-faits"
            @page-change="goToPage"
        />

        <CatalogTable v-if="items.length" caption="Liste des hauts-faits" :columns="COLUMNS" :rows="items" :busy="busy">
            <template #cell-icon="{ row }"><CollectionIcon :src="row.icon_url" :alt="row.name_fr" fallback="HF" size="sm" class="text-muted" /></template>
            <template #cell-name="{ row }">
                <a :href="`https://www.wowhead.com/fr/achievement=${row.id}`" target="_blank" rel="noopener" class="text-default hover:underline">{{ row.name_fr }}</a>
            </template>
            <template #cell-category="{ row }"><span class="text-muted">{{ row.category_name }}</span></template>
            <template #cell-points="{ row }"><span class="tabular-nums text-muted">{{ row.points }} pts</span></template>
        </CatalogTable>
        <EmptyState v-else :icon="SearchX" title="Aucun résultat trouvé" message="Aucune entrée ne correspond à cette recherche." />

        <DatabasePagination
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            label="Pages des hauts-faits"
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
import CollectionIcon from '../components/CollectionIcon.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import { useCatalogPaging } from '../composables/useCatalogPaging';

const COLUMNS = [
    { key: 'icon', label: 'Icône', visuallyHidden: true, class: 'w-12' },
    { key: 'name', label: 'Nom' },
    { key: 'category', label: 'Catégorie', hideBelow: 'sm' },
    { key: 'points', label: 'Points', align: 'end', class: 'w-20' },
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
</script>
