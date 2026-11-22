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
            <meta property="og:locale" :content="$t('locale.og')">
        </Head>

        <DatabasePageHeader
            :title="$t('database.sections.pets.label')"
            :subtitle="activeCategoryName || $t('database.all_categories')"
            :count="displayCount"
            :count-label="$t('database.pets.count_label')"
            dimension="pets"
        />

        <SearchFilter v-model:search="search" :placeholder="$t('database.pets.search')" :show-hide-toggle="false" />

        <DatabasePagination
            placement="top"
            :current-page="page"
            :last-page="pageCount"
            :total="filteredItems.length"
            :label="$t('database.pets.pages')"
            @page-change="goTo"
        />

        <CatalogTable v-if="filteredItems.length" :caption="$t('database.pets.caption')" :columns="COLUMNS" :rows="visibleItems">
            <template #cell-icon="{ row }"><CollectionIcon :src="row.icon_url" :alt="nameOf(row)" fallback="P" size="sm" class="text-muted" /></template>
            <template #cell-name="{ row, linkClass }"><a :class="linkClass" :href="row.creature_id ? wowheadLink(`npc=${row.creature_id}`) : wowheadLink(`search?q=${encodeURIComponent(nameOf(row))}`)" target="_blank" rel="noopener" class="text-default hover:underline">{{ nameOf(row) }}</a></template>
            <template #cell-source="{ row }"><span class="text-muted">{{ row.source ? sourceLabel(COLLECTION, row.source) : $t('database.unknown') }}</span></template>
        </CatalogTable>
        <EmptyState v-else :icon="SearchX" :title="$t('database.empty.title')" :message="$t('database.empty.message')" />

        <DatabasePagination
            :current-page="page"
            :last-page="pageCount"
            :total="filteredItems.length"
            :label="$t('database.pets.pages')"
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
import { useCatalogLocale } from '../composables/useCatalogLocale';
import { useTranslate } from '../composables/useTranslate';

const PER_PAGE = 50;
const COLLECTION = 'pets';

const t = useTranslate();
const { nameOf, categoryLabel, sourceLabel, wowheadLink } = useCatalogLocale();

const COLUMNS = computed(() => [
    { key: 'icon', label: t('database.columns.icon'), visuallyHidden: true, class: 'w-12' },
    { key: 'name', label: t('database.columns.name') },
    { key: 'source', label: t('database.columns.source'), hideBelow: 'sm' },
]);

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
    return cat ? categoryLabel(COLLECTION, cat.name) : '';
});

const displayCount = computed(() => (props.category ? props.items.length : props.total));

const filteredItems = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.items;
    return props.items.filter(i => nameOf(i).toLowerCase().includes(q));
});

const { page, pageCount, visible: visibleItems, goTo } = useClientPaging(filteredItems, PER_PAGE);
</script>
