<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-default">{{ title }}</h3>
            <Pagination v-model:page="page" :page-count="pageCount" :label="`Pages : ${title}`" />
        </div>

        <ul v-if="groups.length" class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Card v-for="(group, index) in visibleGroups" :key="group.name" as="li" data-group class="overflow-hidden">
                <button
                    type="button"
                    :aria-expanded="String(expanded === group.name)"
                    :aria-controls="`${listId}-${index}`"
                    class="w-full space-y-3 p-4 text-left transition-colors duration-fast hover:bg-surface-raised
                        focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
                    @click="expanded = expanded === group.name ? null : group.name"
                >
                    <span class="flex items-start justify-between gap-2">
                        <span data-group-name class="text-sm font-semibold text-default">{{ group.name }}</span>
                        <span class="flex shrink-0 items-center gap-1 text-xs tabular-nums text-subtle">
                            {{ group.completed }} / {{ group.total }}
                            <Icon :icon="ChevronDown" size="sm" class="transition-transform duration-fast" :class="{ 'rotate-180': expanded === group.name }" />
                        </span>
                    </span>
                    <ProgressBar
                        :value="group.completed"
                        :max="Math.max(group.total, 1)"
                        :color="color?.base"
                        :aria-label="`${group.name} : ${group.completed} sur ${group.total}`"
                    />
                </button>
                <ul v-if="expanded === group.name" :id="`${listId}-${index}`" class="max-h-96 space-y-1 overflow-y-auto border-t border-default px-4 py-3">
                    <li v-for="entry in group.items" :key="entry.id" class="flex items-center gap-2 text-sm">
                        <slot name="item" :item="entry" />
                    </li>
                </ul>
            </Card>
        </ul>
        <EmptyState v-else :icon="SearchX" title="Aucun résultat" :message="emptyMessage" />
    </section>
</template>

<script setup>
import { computed, ref, useId, watch } from 'vue';
import { ChevronDown, SearchX } from 'lucide-vue-next';
import { dimensionColor } from '../../utils/wowColors';
import { useWowColor } from '../../composables/useWowColor';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import Icon from '../ui/Icon.vue';
import Pagination from '../ui/Pagination.vue';
import ProgressBar from '../ui/ProgressBar.vue';

const props = defineProps({
    groups: { type: Array, required: true },
    title: { type: String, required: true },
    dimension: { type: String, required: true },
    emptyMessage: { type: String, required: true },
    perPage: { type: Number, default: 8 },
});

const listId = useId();
const page = ref(1);
const expanded = ref(null);
const { safe } = useWowColor();

const color = computed(() => safe(dimensionColor, props.dimension));
const pageCount = computed(() => Math.ceil(props.groups.length / props.perPage));
const visibleGroups = computed(() => props.groups.slice((page.value - 1) * props.perPage, page.value * props.perPage));

watch(() => props.groups, () => {
    page.value = 1;
    expanded.value = null;
});

watch(page, () => {
    expanded.value = null;
});
</script>
