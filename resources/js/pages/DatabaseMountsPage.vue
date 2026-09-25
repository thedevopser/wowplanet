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
            title="Montures"
            :subtitle="activeCategoryName || 'Toutes les catégories'"
            :count="displayCount"
            count-label="montures"
            dimension="mounts"
        />

        <SearchFilter v-model:search="search" placeholder="Rechercher une monture…" :show-hide-toggle="false" />

        <DatabasePagination
            placement="top"
            :current-page="page"
            :last-page="pageCount"
            :total="filteredItems.length"
            label="Pages des montures"
            @page-change="goTo"
        />

        <CatalogTable v-if="filteredItems.length" caption="Liste des montures" :columns="COLUMNS" :rows="visibleItems">
            <template #cell-icon="{ row }"><CollectionIcon :src="row.icon_url" :alt="row.name_fr" fallback="M" size="sm" class="text-muted" /></template>
            <template #cell-name="{ row }"><a :href="row.source_spell_id ? `https://www.wowhead.com/fr/spell=${row.source_spell_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(row.name_fr)}`" target="_blank" rel="noopener" class="text-default hover:underline">{{ row.name_fr }}</a></template>
            <template #cell-source="{ row }"><span class="text-muted">{{ row.source || 'Inconnu' }}</span></template>
        </CatalogTable>
        <EmptyState v-else :icon="SearchX" title="Aucun résultat trouvé" message="Aucune entrée ne correspond à cette recherche." />

        <DatabasePagination
            :current-page="page"
            :last-page="pageCount"
            :total="filteredItems.length"
            label="Pages des montures"
            @page-change="goTo"
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
import { useClientPaging } from '../composables/useClientPaging';

const PER_PAGE = 50;

const COLUMNS = [
    { key: 'icon', label: 'Icône', visuallyHidden: true, class: 'w-12' },
    { key: 'name', label: 'Nom' },
    { key: 'source', label: 'Source', hideBelow: 'sm' },
];

const props = defineProps({
    meta: { type: Object, required: true },
    category: { type: String, default: null },
    items: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
});

const search = ref('');

const activeCategoryName = computed(() => {
    const cat = props.categories.find(c => c.slug === props.category);
    return cat?.name || '';
});

const displayCount = computed(() => (props.category ? props.items.length : props.total));

const filteredItems = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.items;
    return props.items.filter(i => i.name_fr.toLowerCase().includes(q));
});

const { page, pageCount, visible: visibleItems, goTo } = useClientPaging(filteredItems, PER_PAGE);
</script>
