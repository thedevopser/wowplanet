<template>
    <div class="space-y-6">
        <EmptyState v-if="!categories.length" :icon="Inbox" :title="config.title" :message="config.empty" />

        <template v-else>
            <Select v-model="categoryParam" label="Catégorie" :options="categoryOptions" />

            <ProgressSummary
                :title="config.title"
                :description="translateCategory(kind, current.name)"
                :completed="current.completed"
                :total="current.total"
                :dimension="config.dimension"
            />

            <SearchFilter v-model:search="search" v-model:hide-completed="hideCompleted" :placeholder="config.search" :hide-label="config.hide" />

            <section v-if="current.name === UNCATEGORIZED" class="space-y-4">
                <h3 class="text-lg font-semibold text-default">{{ config.uncategorized }}</h3>
                <ul v-if="looseItems.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="entry in looseItems" :key="entry.id" as="li" data-loose-item class="flex items-center gap-3 p-3">
                        <CollectionIcon :src="entry.icon_url" alt="" :fallback="config.title.charAt(0)" size="lg" />
                        <a :href="config.wowheadUrl(entry)" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-sm font-semibold hover:underline" :class="entry.is_completed ? 'text-default' : 'text-muted'">{{ entry.name }}</a>
                        <CompletionMark :done="entry.is_completed" />
                    </Card>
                </ul>
                <EmptyState v-else :icon="SearchX" title="Aucun résultat" message="Aucun élément ne correspond à ces filtres." />
            </section>

            <GroupedChecklist v-else :groups="sources" title="Sources" :dimension="config.dimension" empty-message="Aucun élément ne correspond à ces filtres.">
                <template #item="{ item }">
                    <CollectionIcon :src="item.icon_url" alt="" :fallback="config.title.charAt(0)" />
                    <a
                        :href="config.wowheadUrl(item)"
                        target="_blank"
                        rel="noopener"
                        class="min-w-0 flex-1 truncate py-1 hover:underline"
                        :class="item.is_completed ? 'text-default' : 'text-muted'"
                    >{{ item.name }}</a>
                    <CompletionMark :done="item.is_completed" />
                </template>
            </GroupedChecklist>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Inbox, SearchX } from 'lucide-vue-next';
import { useCharacterStore } from '../../stores/character';
import { useQueryParam } from '../../composables/useQueryParam';
import { useWowColor } from '../../composables/useWowColor';
import { useWowheadTooltips } from '../../composables/useWowheadTooltips';
import { COLLECTIONS, UNCATEGORIZED, groupCollection, translateCategory, translateSource } from '../../utils/collections';
import { dimensionColor } from '../../utils/wowColors';
import CollectionIcon from '../CollectionIcon.vue';
import SearchFilter from '../SearchFilter.vue';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import Select from '../ui/Select.vue';
import CompletionMark from './CompletionMark.vue';
import GroupedChecklist from './GroupedChecklist.vue';
import ProgressSummary from './ProgressSummary.vue';

const props = defineProps({
    kind: { type: String, required: true, validator: (value) => Object.hasOwn(COLLECTIONS, value) },
    character: { type: Object, required: true },
});

useWowheadTooltips();
const store = useCharacterStore();
const { safe } = useWowColor();

const config = computed(() => COLLECTIONS[props.kind]);

const categories = computed(() => groupCollection(
    props.character[config.value.field] ?? [],
    [...store.expansionNamesDesc, ...config.value.extraCategories],
));

const categoryParam = useQueryParam('categorie', categories.value[0]?.name ?? '');
const current = computed(() => categories.value.find((category) => category.name === categoryParam.value) ?? categories.value[0]);

const formatNumber = (value) => Number(value).toLocaleString('fr-FR');
const barColor = computed(() => safe(dimensionColor, config.value.dimension)?.base);

const categoryOptions = computed(() => categories.value.map((category) => ({
    value: category.name,
    label: translateCategory(props.kind, category.name),
    hint: `${formatNumber(category.completed)} / ${formatNumber(category.total)}`,
    progress: { value: category.completed, max: category.total, color: barColor.value },
})));

const search = ref('');
const hideCompleted = ref(false);

watch(() => current.value?.name, () => {
    search.value = '';
});

const byName = (a, b) => a.name.localeCompare(b.name, 'fr');

function keep(items) {
    const query = search.value.trim().toLowerCase();

    return items
        .filter((item) => !query || item.name.toLowerCase().includes(query))
        .filter((item) => !hideCompleted.value || !item.is_completed)
        .toSorted(byName);
}

const sources = computed(() => (current.value?.sources ?? [])
    .map((source) => {
        const items = keep(source.items);

        return { name: translateSource(props.kind, source.name), items, total: items.length, completed: items.filter((item) => item.is_completed).length };
    })
    .filter((source) => source.items.length > 0)
    .toSorted(byName));

const looseItems = computed(() => keep(current.value?.items ?? []));
</script>
