<template>
    <div class="space-y-6">
        <ExpansionFilter
            v-model="expansionId"
            :expansions="store.expansions"
            :collections="store.character.collections ?? {}"
            :collection-type="collectionType"
        />

        <template v-if="collection">
            <ProgressSummary
                :title="labels.title"
                :description="labels.description"
                :completed="collection.completed"
                :total="collection.total"
                :dimension="collectionType"
            />

            <SearchFilter
                v-model:search="search"
                v-model:hide-completed="hideCompleted"
                :placeholder="labels.search"
                :hide-label="labels.hide"
            />

            <GroupedChecklist :groups="groups" :title="labels.groups" :dimension="collectionType" :empty-message="labels.noMatch">
                <template #item="{ item }">
                    <CollectionIcon v-if="withIcon" :src="item.icon_url" :alt="''" fallback="" />
                    <a
                        :href="itemUrl(item)"
                        target="_blank"
                        rel="noopener"
                        class="min-w-0 flex-1 truncate py-1 hover:underline"
                        :class="item.is_completed ? 'text-default' : 'text-muted'"
                    >{{ item.name }}</a>
                    <CompletionMark :done="item.is_completed" :elsewhere="isElsewhere(item.id)" :owner="ownerOf(item.id) ?? ''" />
                </template>
            </GroupedChecklist>
        </template>
        <EmptyState v-else :icon="Inbox" title="Rien à afficher" :message="labels.noData" />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Inbox } from 'lucide-vue-next';
import { useCharacterStore } from '../../stores/character';
import { useQueryParam } from '../../composables/useQueryParam';
import { useWowheadTooltips } from '../../composables/useWowheadTooltips';
import CollectionIcon from '../CollectionIcon.vue';
import SearchFilter from '../SearchFilter.vue';
import EmptyState from '../ui/EmptyState.vue';
import CompletionMark from './CompletionMark.vue';
import ExpansionFilter from './ExpansionFilter.vue';
import GroupedChecklist from './GroupedChecklist.vue';
import ProgressSummary from './ProgressSummary.vue';

const props = defineProps({
    collectionType: { type: String, required: true, validator: (value) => ['quests', 'achievements'].includes(value) },
    groupsKey: { type: String, required: true },
    labels: { type: Object, required: true },
    itemUrl: { type: Function, required: true },
    isElsewhere: { type: Function, required: true },
    ownerOf: { type: Function, required: true },
    withIcon: { type: Boolean, default: false },
});

useWowheadTooltips();
const store = useCharacterStore();

const extension = useQueryParam('extension', String(store.latestExpansionId));
const expansionId = computed({
    get: () => Number(extension.value),
    set: (value) => {
        extension.value = String(value);
    },
});

const search = ref('');
const hideCompleted = ref(false);

watch(expansionId, () => {
    search.value = '';
});

const collection = computed(() => store.character?.collections?.[expansionId.value]?.[props.collectionType] ?? null);

const byName = (a, b) => a.name.localeCompare(b.name, 'fr');

const groups = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (collection.value?.[props.groupsKey] ?? [])
        .map((group) => {
            const items = (group.items ?? [])
                .filter((item) => !query || item.name.toLowerCase().includes(query))
                .filter((item) => !hideCompleted.value || !item.is_completed)
                .toSorted(byName);

            return { name: group.name, items, total: items.length, completed: items.filter((item) => item.is_completed).length };
        })
        .filter((group) => group.items.length > 0)
        .toSorted(byName);
});
</script>
