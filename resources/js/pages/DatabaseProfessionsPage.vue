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

        <template v-if="!profession">
            <DatabasePageHeader
                :title="$t('database.sections.professions.label')"
                :subtitle="$t('database.professions.subtitle')"
                :count="total_recipes"
                :count-label="$t('database.professions.count_label')"
                dimension="professions"
            />

            <section v-for="group in professionGroups" :key="group.title" class="space-y-3">
                <h2 class="text-lg font-semibold text-default">{{ group.title }}</h2>
                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="prof in group.professions" :key="prof.id" as="li" variant="interactive" class="flex items-center gap-4 p-4">
                        <span
                            aria-hidden="true"
                            class="flex size-12 shrink-0 items-center justify-center rounded-ui-md border border-default bg-surface-raised text-lg font-bold"
                            :style="{ color: readable(PROFESSION_COLOR) }"
                        >{{ professionLabel(prof).charAt(0) }}</span>
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="localizedUrl(`/base-de-donnees/professions/${prof.slug}`)"
                                class="block truncate font-semibold text-default hover:underline focus-visible:outline-none after:absolute after:inset-0"
                            >{{ professionLabel(prof) }}</Link>
                            <p class="text-sm tabular-nums text-muted">{{ $t('database.professions.recipe_count', { count: prof.recipe_count.toLocaleString(locale) }) }}</p>
                        </div>
                    </Card>
                </ul>
            </section>
        </template>

        <template v-else>
            <DatabasePageHeader
                :title="professionName"
                :subtitle="activeExpansionName || $t('database.all_expansions')"
                :count="recipeTotal"
                :count-label="$t('database.professions.count_label')"
                dimension="professions"
            />

            <div v-if="recipeExpansions.length" role="group" aria-label="Extension" class="flex flex-wrap gap-2">
                <Button
                    v-for="exp in recipeExpansions"
                    :key="exp.slug"
                    size="sm"
                    :variant="activeExpansion === exp.slug ? 'secondary' : 'ghost'"
                    :data-expansion="exp.slug"
                    :aria-pressed="String(activeExpansion === exp.slug)"
                    @click="toggleExpansion(exp.slug)"
                >
                    {{ exp.name }}
                    <span class="tabular-nums text-subtle">{{ exp.count }}</span>
                </Button>
            </div>

            <SearchFilter
                v-model:search="search"
                :placeholder="$t('database.professions.search')"
                :show-hide-toggle="false"
                :debounce-ms="300"
                @search-debounced="searchFor"
            />

            <DatabasePagination
                placement="top"
                :current-page="currentPage"
                :last-page="lastPage"
                :total="recipeTotal"
                :label="$t('database.professions.pages', { profession: professionName })"
                @page-change="goToPage"
            />

            <CatalogTable v-if="recipeList.length" :caption="`Recettes : ${professionName}`" :columns="COLUMNS" :rows="recipeList" :busy="busy">
                <template #cell-name="{ row, linkClass }">
                    <a
                        :href="row.wowhead_spell_id ? wowheadLink(`spell=${row.wowhead_spell_id}`) : wowheadLink(`search?q=${encodeURIComponent(nameOf(row))}`)"
                        target="_blank"
                        rel="noopener"
                        class="text-default hover:underline"
                        :class="linkClass"
                    >{{ nameOf(row) }}</a>
                </template>
                <template #cell-category="{ row }"><span class="text-muted">{{ categoryNameOf(row) }}</span></template>
                <template #cell-faction="{ row }">
                    <template v-if="row.faction">
                        <Badge v-if="hasFactionColor(row.faction)" tone="faction" :value="row.faction">{{ row.faction }}</Badge>
                        <Badge v-else>{{ row.faction }}</Badge>
                    </template>
                </template>
            </CatalogTable>
            <EmptyState v-else :icon="SearchX" :title="$t('database.empty.title')" :message="$t('database.professions.empty')" />

            <DatabasePagination
                :current-page="currentPage"
                :last-page="lastPage"
                :total="recipeTotal"
                :label="$t('database.professions.pages', { profession: professionName })"
                @page-change="goToPage"
            />
        </template>
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
import { useCatalogLocale } from '../composables/useCatalogLocale';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useTranslate } from '../composables/useTranslate';
import { Head, Link } from '@inertiajs/vue3';
import { SearchX } from 'lucide-vue-next';
import CatalogTable from '../components/CatalogTable.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';
import SearchFilter from '../components/SearchFilter.vue';
import Badge from '../components/ui/Badge.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import { useCatalogPaging } from '../composables/useCatalogPaging';
import { useWowColor } from '../composables/useWowColor';
import { dimensionColor, factionColor } from '../utils/wowColors';

const t = useTranslate();
const { nameOf, wowheadLink, categoryNameOf } = useCatalogLocale();
const { localizedUrl } = useLocalizedUrl();
const locale = useActiveLocale();
const professionLabel = (profession) => profession.name ?? profession.name_fr;

const COLUMNS = computed(() => [
    { key: 'name', label: t('database.columns.name') },
    { key: 'category', label: t('database.columns.category'), hideBelow: 'sm' },
    { key: 'faction', label: t('database.columns.faction'), align: 'end', hideBelow: 'sm', class: 'w-28' },
]);

const PROFESSION_COLOR = dimensionColor('professions');

const props = defineProps({
    meta: { type: Object, required: true },
    profession: { type: String, default: null },
    expansion: { type: String, default: null },
    search: { type: String, default: null },
    professions: { type: Array, default: () => [] },
    total_recipes: { type: Number, default: 0 },
    // Profession detail: { items, expansions, profession, total, current_page, last_page }, or null on the list.
    recipes: { type: Object, default: null },
});

const { readable, safe } = useWowColor();
const search = ref(props.search ?? '');

const professionGroups = computed(() => [
    { title: t('database.professions.primary'), professions: props.professions.filter((prof) => prof.type === 'primary') },
    { title: t('database.professions.secondary'), professions: props.professions.filter((prof) => prof.type === 'secondary') },
].filter((group) => group.professions.length > 0));

const recipeList = computed(() => props.recipes?.items ?? []);
const recipeExpansions = computed(() => props.recipes?.expansions ?? []);
const professionName = computed(() => props.recipes?.profession?.name ?? props.recipes?.profession?.name_fr ?? '');
const recipeTotal = computed(() => props.recipes?.total ?? 0);
const currentPage = computed(() => props.recipes?.current_page ?? 1);
const lastPage = computed(() => props.recipes?.last_page ?? 1);
const activeExpansion = computed(() => props.expansion ?? '');
const activeExpansionName = computed(() => recipeExpansions.value.find((exp) => exp.slug === activeExpansion.value)?.name ?? '');

const { busy, goToPage, searchFor, reloadWith } = useCatalogPaging(
    ['recipes', 'expansion', 'search'],
    search,
    () => ({ expansion: activeExpansion.value || undefined }),
);

function toggleExpansion(slug) {
    reloadWith({ expansion: activeExpansion.value === slug ? undefined : slug });
}

const hasFactionColor = (faction) => safe(factionColor, faction) !== null;
</script>
