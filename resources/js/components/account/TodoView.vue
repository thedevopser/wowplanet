<template>
    <div class="space-y-6">
        <Card v-if="status === 'computing'" data-computing class="mx-auto max-w-xl space-y-4 p-5 sm:p-6">
            <template v-if="step === 'account'">
                <div role="status" aria-live="polite" class="space-y-1">
                    <p class="text-lg font-semibold text-default">Analyse de {{ progress.current }}…</p>
                    <p class="text-sm text-muted">{{ progress.loaded }} / {{ progress.total }} personnages chargés</p>
                </div>
                <ProgressBar
                    :value="processed"
                    :max="Math.max(progress.total, 1)"
                    :aria-label="`Personnages analysés : ${processed} sur ${progress.total}`"
                />
            </template>
            <div v-else role="status" aria-live="polite" class="flex items-center gap-3">
                <Spinner />
                <p class="text-lg font-semibold text-default">Comparaison de vos personnages…</p>
            </div>
            <p class="text-xs text-subtle">La liste se prépare une fois, puis reste en mémoire.</p>
        </Card>

        <CardGridSkeleton v-else-if="status === 'loading'" label="Chargement de votre liste" :count="3" />

        <ErrorState v-else-if="status === 'error'" title="Liste indisponible" :message="errorMessage" @retry="start" />

        <EmptyState
            v-else-if="todo.categories.length === 0"
            :icon="PartyPopper"
            title="Rien à faire"
            message="Aucune collection, quête, réputation ou recette entamée n’attend vos personnages."
        />

        <template v-else>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <Select v-model="character" label="Personnage" :options="characters" />
                <div class="flex flex-wrap items-center gap-3">
                    <p v-if="updatedAt" data-updated-at class="text-xs text-subtle">Mis à jour le {{ updatedAt }}</p>
                    <Button variant="secondary" :disabled="refreshing" @click="refresh">
                        <Icon :icon="RotateCw" size="sm" :class="{ 'motion-safe:animate-spin': refreshing }" />
                        Mettre à jour
                    </Button>
                </div>
            </div>

            <template v-if="dimension === ALL">
                <section data-nearly-done class="space-y-4">
                    <SectionHeader title="Presque fini" :level="2" description="Les catégories les plus proches de la fin, toutes dimensions confondues." />
                    <ul v-if="closest.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <TodoCategoryCard v-for="entry in closest" :key="entry.key" :category="entry" :subtitle="subtitleOf(entry)" :roster="todo.characters" />
                    </ul>
                    <EmptyState v-else :icon="Filter" title="Rien pour ce personnage" message="Aucun élément entamé n’attend ce personnage." />
                </section>

                <section class="space-y-4">
                    <SectionHeader title="Par dimension" :level="2" description="Tout ce qui reste, dimension par dimension." />
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <li v-for="summary in summaries" :key="summary.dimension">
                            <button
                                type="button"
                                data-dimension-tile
                                class="flex min-h-11 w-full items-center gap-3 rounded-ui-md border border-default bg-surface p-4 text-left transition-colors duration-fast
                                    hover:bg-surface-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                @click="openDimension(summary.dimension)"
                            >
                                <span aria-hidden="true" class="h-10 w-1 shrink-0 rounded-full" :style="{ backgroundColor: readable(todoColor(summary.dimension)) }" />
                                <span class="min-w-0 flex-1">
                                    <span data-dimension-label class="block text-sm font-semibold text-default">{{ summary.label }}</span>
                                    <span class="block text-xs tabular-nums text-subtle">
                                        {{ summary.categories }} catégorie{{ summary.categories > 1 ? 's' : '' }} · {{ summary.missing }} manquant{{ summary.missing > 1 ? 's' : '' }}
                                    </span>
                                </span>
                                <Icon :icon="ChevronRight" size="sm" class="shrink-0 text-subtle" />
                            </button>
                        </li>
                    </ul>
                </section>
            </template>

            <section v-else class="space-y-4">
                <button
                    type="button"
                    data-back
                    class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-muted hover:text-default lg:min-h-0"
                    @click="openDimension(ALL)"
                >
                    <Icon :icon="ChevronLeft" size="sm" />
                    Vue d’ensemble
                </button>
                <SectionHeader :title="dimensionLabel(dimension)" :level="2" :description="dimensionDescription" />

                <div v-if="expansions.length" role="group" aria-label="Extension" class="relative -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
                    <button
                        v-for="option in expansions"
                        :key="option.value"
                        type="button"
                        data-expansion
                        :aria-pressed="String(option.value === expansion)"
                        class="inline-flex h-11 shrink-0 items-center gap-2 rounded-ui-md border px-3 text-sm transition-colors duration-fast lg:h-9
                            focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        :class="option.value === expansion ? 'border-accent bg-accent/10 font-semibold text-default' : 'border-default bg-surface text-muted hover:bg-surface-raised'"
                        @click="expansion = option.value"
                    >
                        {{ option.label }}
                        <span class="text-xs tabular-nums text-subtle">{{ option.count }}</span>
                    </button>
                </div>

                <ul v-if="paging.visible.value.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <TodoCategoryCard v-for="entry in paging.visible.value" :key="entry.key" :category="entry" :subtitle="subtitleOf(entry)" :roster="todo.characters" />
                </ul>
                <EmptyState v-else :icon="Filter" title="Rien pour ce personnage" message="Aucun élément de cette dimension n’attend ce personnage." />

                <Pagination :page="paging.page.value" :page-count="paging.pageCount.value" label="Pages de la dimension" @update:page="paging.goTo" />
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, Filter, PartyPopper, RotateCw } from 'lucide-vue-next';
import { useAccountComputation } from '../../composables/useAccountComputation';
import { useClientPaging } from '../../composables/useClientPaging';
import { useQueryParam, useQueryParamsUpdate } from '../../composables/useQueryParam';
import { useWowColor } from '../../composables/useWowColor';
import { useCharacterStore } from '../../stores/character';
import { ALL, characterOptions, dimensionLabel, dimensionSummaries, expansionOptions, filterTodo, nearlyDone, todoColor } from '../../utils/accountTodo';
import CardGridSkeleton from '../CardGridSkeleton.vue';
import Button from '../ui/Button.vue';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import Icon from '../ui/Icon.vue';
import Pagination from '../ui/Pagination.vue';
import ProgressBar from '../ui/ProgressBar.vue';
import SectionHeader from '../ui/SectionHeader.vue';
import Select from '../ui/Select.vue';
import Spinner from '../ui/Spinner.vue';
import TodoCategoryCard from './TodoCategoryCard.vue';

const NEARLY_DONE_COUNT = 6;
const PER_PAGE = 12;

const DIMENSION_PARAM = 'dimension';
const EXPANSION_PARAM = 'extension';

const store = useCharacterStore();
const { readable } = useWowColor();
const updateQuery = useQueryParamsUpdate();

const EMPTY_PROGRESS = Object.freeze({ loaded: 0, errors: 0, total: 0, current: '' });
const EMPTY_TODO = Object.freeze({ categories: [], characters: [], updatedAt: null });

const { status, computing, data, errorMessage, refreshing, start, refresh } = useAccountComputation({
    endpoint: '/api/account/todo',
    refreshEndpoint: '/api/account/todo/refresh',
    messages: { failed: 'Le calcul de vos personnages a échoué. Réessayez dans un instant.', unavailable: 'La liste n’a pas pu être chargée.' },
});

const step = computed(() => computing.value?.step ?? 'account');
const progress = computed(() => computing.value?.progress ?? EMPTY_PROGRESS);
const todo = computed(() => data.value ?? EMPTY_TODO);

const processed = computed(() => Math.min(progress.value.loaded + progress.value.errors, progress.value.total));

const expansionNames = computed(() => new Map(store.expansions.map((entry) => [entry.id, entry.name])));

// A filter of the address the list does not offer falls back to the whole list.
const knownOr = (param, options) => computed({
    get: () => (options.value.some((option) => option.value === param.value) ? param.value : ALL),
    set: (value) => {
        param.value = value;
    },
});

const characters = computed(() => characterOptions(todo.value.characters));
const character = knownOr(useQueryParam('personnage', ALL), characters);

const forCharacter = computed(() => filterTodo(todo.value.categories, { dimension: ALL, character: character.value }));
const summaries = computed(() => dimensionSummaries(forCharacter.value));
const closest = computed(() => nearlyDone(forCharacter.value, NEARLY_DONE_COUNT));

const dimensionParam = useQueryParam(DIMENSION_PARAM, ALL);
const dimension = computed(() => (todo.value.categories.some((entry) => entry.dimension === dimensionParam.value) ? dimensionParam.value : ALL));

const inDimension = computed(() => filterTodo(forCharacter.value, { dimension: dimension.value, character: ALL }));
const expansions = computed(() => expansionOptions(inDimension.value, expansionNames.value));
const expansion = knownOr(useQueryParam(EXPANSION_PARAM, ALL), expansions);

const shown = computed(() => filterTodo(inDimension.value, { dimension: ALL, character: ALL, expansion: expansion.value }));
const paging = useClientPaging(shown, PER_PAGE);

const dimensionDescription = computed(() => {
    const count = inDimension.value.length;

    return `${count} catégorie${count > 1 ? 's' : ''} entamée${count > 1 ? 's' : ''}, de la plus proche de la fin à la plus lointaine.`;
});

const updatedAt = computed(() => {
    if (!todo.value.updatedAt) return '';

    return new Date(todo.value.updatedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
});

function openDimension(target) {
    updateQuery([
        { name: DIMENSION_PARAM, value: target, fallback: ALL },
        { name: EXPANSION_PARAM, value: ALL, fallback: ALL },
    ]);
}

function subtitleOf(category) {
    const label = dimensionLabel(category.dimension);
    const expansionName = expansionNames.value.get(category.expansionId);

    return expansionName ? `${label} · ${expansionName}` : label;
}

</script>
