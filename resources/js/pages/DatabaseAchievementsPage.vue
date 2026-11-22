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
            :title="$t('database.sections.achievements.label')"
            :subtitle="activeExpansionName || $t('database.all_expansions')"
            :count="total"
            :count-label="$t('database.achievements.count_label')"
            dimension="achievements"
        />

        <SearchFilter
            v-model:search="search"
            :placeholder="$t('database.achievements.search')"
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="searchFor"
        />

        <DatabasePagination
            placement="top"
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            :label="$t('database.achievements.pages')"
            @page-change="goToPage"
        />

        <CatalogTable v-if="items.length" :caption="$t('database.achievements.caption')" :columns="COLUMNS" :rows="items" :busy="busy">
            <template #cell-icon="{ row }"><CollectionIcon :src="row.icon_url" :alt="nameOf(row)" fallback="HF" size="sm" class="text-muted" /></template>
            <template #cell-name="{ row, linkClass }">
                <a :class="linkClass" :href="wowheadLink(`achievement=${row.id}`)" target="_blank" rel="noopener" class="text-default hover:underline">{{ nameOf(row) }}</a>
            </template>
            <template #cell-category="{ row }"><span class="text-muted">{{ row.category_name }}</span></template>
            <template #cell-points="{ row }"><span class="tabular-nums text-muted">{{ $t('database.points', { points: String(row.points) }) }}</span></template>
        </CatalogTable>
        <EmptyState v-else :icon="SearchX" :title="$t('database.empty.title')" :message="$t('database.empty.message')" />

        <DatabasePagination
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            :label="$t('database.achievements.pages')"
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
import { useCatalogLocale } from '../composables/useCatalogLocale';
import { useTranslate } from '../composables/useTranslate';

const t = useTranslate();
const { nameOf, wowheadLink } = useCatalogLocale();

const COLUMNS = computed(() => [
    { key: 'icon', label: t('database.columns.icon'), visuallyHidden: true, class: 'w-12' },
    { key: 'name', label: t('database.columns.name') },
    { key: 'category', label: t('database.columns.category'), hideBelow: 'sm' },
    { key: 'points', label: t('database.columns.points'), align: 'end', class: 'w-20' },
]);

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
