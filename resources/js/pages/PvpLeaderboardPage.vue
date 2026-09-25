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

        <header class="flex flex-wrap items-end justify-between gap-4 rounded-ui-md border border-l-4 border-default border-l-accent bg-surface p-5 sm:p-6">
            <div class="min-w-0">
                <h1 class="font-display text-2xl font-bold text-default sm:text-3xl">Classements PvP</h1>
                <p class="mt-1 text-sm text-muted sm:text-base">{{ seasonId ? `${label} — saison ${seasonId}` : label }}</p>
            </div>
            <p class="text-right">
                <span class="block text-2xl font-bold tabular-nums text-default sm:text-3xl">{{ total.toLocaleString('fr-FR') }}</span>
                <span class="text-sm text-muted">joueurs classés</span>
            </p>
        </header>

        <!-- Solo shuffle and Blitz hold about forty brackets each: they go in a list, not in buttons. -->
        <div v-if="groups.length" class="space-y-3">
            <div role="group" aria-label="Mode" class="flex flex-wrap gap-2">
                <Button
                    v-for="group in groups"
                    :key="group.key"
                    :data-testid="`pvp-mode-${group.key}`"
                    :variant="group.key === activeGroupKey ? 'secondary' : 'ghost'"
                    :aria-pressed="String(group.key === activeGroupKey)"
                    @click="onModeChange(group)"
                >{{ group.label }}</Button>
            </div>

            <template v-if="activeGroup && activeGroup.brackets.length > 1">
                <div v-if="activeGroup.brackets.length <= INLINE_OPTIONS_MAX" role="group" aria-label="Format" class="flex flex-wrap gap-2">
                    <Button
                        v-for="option in activeGroup.brackets"
                        :key="option.slug"
                        size="sm"
                        :data-testid="`pvp-bracket-option-${option.slug}`"
                        :variant="option.slug === bracket ? 'secondary' : 'ghost'"
                        :aria-pressed="String(option.slug === bracket)"
                        @click="onBracketChange(option.slug)"
                    >{{ option.short }}</Button>
                </div>
                <Select v-else :model-value="bracket" label="Spécialisation" :options="bracketOptions" @update:model-value="onBracketChange" />
            </template>
        </div>

        <SearchFilter
            v-model:search="searchTerm"
            placeholder="Rechercher un joueur ou un royaume…"
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="searchFor"
        />

        <ErrorState
            v-if="unavailable"
            title="Classement momentanément indisponible"
            message="L’API Blizzard n’a pas répondu, réessayez dans quelques minutes."
            @retry="router.reload()"
        />

        <template v-else>
            <DatabasePagination
                placement="top"
                :current-page="currentPage"
                :last-page="lastPage"
                :total="total"
                label="Pages du classement"
                @page-change="goToPage"
            />

            <CatalogTable
                v-if="entries.length"
                :caption="`Classement ${label}`"
                :columns="COLUMNS"
                :rows="entries"
                :row-attrs="(entry) => ({ 'data-testid': `pvp-rank-${entry.rank}` })"
                row-key="rank"
                :busy="busy"
            >
                <template #cell-rank="{ row }"><span class="tabular-nums text-muted">{{ row.rank }}</span></template>
                <template #cell-player="{ row }">
                    <Link
                        :href="`/character/${row.realm_slug}/${row.name.toLowerCase()}`"
                        class="font-medium hover:underline"
                        :style="factionStyle(row.faction)"
                    >{{ row.name }}</Link>
                    <span class="ml-1 text-xs text-muted sm:hidden">{{ row.realm }}</span>
                </template>
                <template #cell-realm="{ row }"><span class="text-muted">{{ row.realm }}</span></template>
                <template #cell-rating="{ row }"><span class="font-bold tabular-nums text-default">{{ row.rating }}</span></template>
                <template #cell-record="{ row }">
                    <span class="tabular-nums text-muted">
                        <span class="text-success">{{ row.won }} V</span> / <span class="text-danger">{{ row.lost }} D</span>
                    </span>
                </template>
            </CatalogTable>
            <EmptyState v-else :icon="SearchX" title="Aucun résultat" message="Aucun joueur ni royaume ne correspond à cette recherche." />

            <DatabasePagination
                :current-page="currentPage"
                :last-page="lastPage"
                :total="total"
                label="Pages du classement"
                @page-change="goToPage"
            />
        </template>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { SearchX } from 'lucide-vue-next';
import CatalogTable from '../components/CatalogTable.vue';
import DatabasePagination from '../components/DatabasePagination.vue';
import SearchFilter from '../components/SearchFilter.vue';
import Button from '../components/ui/Button.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import ErrorState from '../components/ui/ErrorState.vue';
import Select from '../components/ui/Select.vue';
import { useCatalogPaging } from '../composables/useCatalogPaging';
import { useWowColor } from '../composables/useWowColor';
import { factionColor } from '../utils/wowColors';

const INLINE_OPTIONS_MAX = 6;

const COLUMNS = [
    { key: 'rank', label: 'Rang', class: 'w-16' },
    { key: 'player', label: 'Joueur' },
    { key: 'realm', label: 'Royaume', hideBelow: 'sm' },
    { key: 'rating', label: 'Cote', align: 'end' },
    { key: 'record', label: 'Victoires / défaites', align: 'end', hideBelow: 'md' },
];

const props = defineProps({
    meta: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    bracket: { type: String, required: true },
    label: { type: String, default: '' },
    seasonId: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    unavailable: { type: Boolean, default: false },
    search: { type: String, default: null },
});

const { readable, safe } = useWowColor();
const searchTerm = ref(props.search ?? '');

const activeGroup = computed(
    () => props.groups.find((group) => group.brackets.some((option) => option.slug === props.bracket)) ?? props.groups[0] ?? null,
);
const activeGroupKey = computed(() => activeGroup.value?.key ?? null);
const bracketOptions = computed(() => (activeGroup.value?.brackets ?? []).map((option) => ({ value: option.slug, label: option.short })));

// The whole ranking travels in props: only these keys are reloaded.
const { busy, goToPage, searchFor } = useCatalogPaging(
    ['entries', 'total', 'currentPage', 'lastPage', 'unavailable', 'search', 'bracket', 'label'],
    searchTerm,
);

function factionStyle(faction) {
    const color = safe(factionColor, faction);

    return color ? { color: readable(color) } : undefined;
}

// Another bracket is another page: search and pagination start over.
function onBracketChange(slug) {
    if (slug === props.bracket) return;

    searchTerm.value = '';
    router.get(`/classements-pvp/${slug}`, {}, { preserveState: false });
}

// A mode opens on its "all specs" ranking when Blizzard publishes one, otherwise on its first bracket.
function onModeChange(group) {
    if (group.key === activeGroupKey.value) return;

    onBracketChange(group.brackets[0].slug);
}
</script>
