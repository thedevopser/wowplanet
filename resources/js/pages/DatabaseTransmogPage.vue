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
            title="Garde-robe"
            :subtitle="activeSlotName || 'Tous les emplacements'"
            :count="total"
            count-label="apparences"
            dimension="transmog"
        />

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher une apparence…"
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="searchFor"
        />

        <DatabasePagination
            placement="top"
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            label="Pages des apparences"
            @page-change="goToPage"
        />

        <CatalogTable v-if="items.length" caption="Liste des apparences" :columns="COLUMNS" :rows="items" :busy="busy">
            <template #cell-icon="{ row }"><CollectionIcon :src="row.icon_url" :alt="row.name_fr" fallback="?" size="sm" class="text-muted" /></template>
            <template #cell-name="{ row }">
                <a
                    :href="row.item_id ? `https://www.wowhead.com/fr/item=${row.item_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(row.name_fr)}`"
                    target="_blank"
                    rel="noopener"
                    class="text-default hover:underline"
                >{{ row.name_fr }}</a>
            </template>
            <template #cell-slot="{ row }"><span class="text-muted">{{ slotLabel(row.slot) }}</span></template>
            <template #cell-category="{ row }"><span class="text-muted">{{ row.category }}</span></template>
        </CatalogTable>
        <EmptyState v-else :icon="SearchX" title="Aucun résultat trouvé" message="Aucune entrée ne correspond à cette recherche." />

        <DatabasePagination
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            label="Pages des apparences"
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
    { key: 'slot', label: 'Emplacement', hideBelow: 'sm' },
    { key: 'category', label: 'Catégorie' },
];

const SLOT_FR = {
    HEAD: 'Tête', SHOULDER: 'Épaules', SHIRT: 'Chemise', CHEST: 'Torse', WAIST: 'Ceinture',
    LEGS: 'Jambes', FEET: 'Pieds', WRIST: 'Poignets', HAND: 'Mains', CLOAK: 'Cape', TABARD: 'Tabard',
    WEAPON: 'Arme', SHIELD: 'Bouclier', RANGED: 'Distance', TWOHWEAPON: 'Arme à deux mains',
    WEAPONOFFHAND: 'Arme en main gauche', HOLDABLE: 'Tenu en main gauche',
};

const props = defineProps({
    meta: { type: Object, required: true },
    slot: { type: String, default: null },
    search: { type: String, default: null },
    items: { type: Array, default: () => [] },
    slots: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    current_page: { type: Number, default: 1 },
    last_page: { type: Number, default: 1 },
});

const search = ref(props.search ?? '');

function slotLabel(slot) {
    return SLOT_FR[slot] || slot;
}

const activeSlotName = computed(() => {
    const s = props.slots.find(x => x.slug === props.slot);
    return s ? slotLabel(s.name) : '';
});

const dataOnly = ['items', 'slots', 'total', 'current_page', 'last_page', 'search'];

const { busy, goToPage, searchFor } = useCatalogPaging(dataOnly, search);
</script>
