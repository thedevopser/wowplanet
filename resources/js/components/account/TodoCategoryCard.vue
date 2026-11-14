<template>
    <Card as="li" data-todo-category class="flex flex-col gap-3 p-4">
        <div class="flex items-start gap-3">
            <span
                data-missing
                class="flex size-10 shrink-0 items-center justify-center rounded-ui-sm bg-surface-raised text-sm font-bold tabular-nums"
                :style="{ color }"
                :title="`${category.missing} manquant${category.missing > 1 ? 's' : ''}`"
            >{{ category.missing }}</span>
            <div class="min-w-0 flex-1">
                <h3 data-category-name class="text-sm font-semibold text-default">{{ category.name }}</h3>
                <p class="text-xs text-subtle">{{ subtitle }}</p>
            </div>
        </div>

        <div class="space-y-1">
            <ProgressBar :value="category.completed" :max="category.total" :color="color" :aria-label="`${category.name} : ${category.completed} sur ${category.total}`" />
            <p class="text-right text-xs tabular-nums text-subtle">{{ category.completed }} / {{ category.total }}</p>
        </div>

        <ul :id="listId" class="divide-y divide-default border-t border-default">
            <li v-for="entry in shownItems" :key="entry.id" data-todo-item class="relative flex min-h-11 flex-wrap items-center justify-between gap-x-3 gap-y-0.5 py-2 text-sm hover:bg-surface-raised lg:min-h-0">
                <span class="min-w-0">
                    <a :href="entry.wowheadUrl" target="_blank" rel="noopener" class="text-default outline-none after:absolute after:inset-0 hover:underline focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-accent">{{ entry.name }}</a>
                    <span v-if="entry.detail" class="ml-2 text-xs text-subtle">{{ entry.detail }}</span>
                </span>
                <span data-doers class="text-xs text-muted">{{ doersLabel(entry.doers, roster) }}</span>
            </li>
        </ul>

        <p v-if="unfolded && category.missingMore > 0" class="text-xs text-subtle">
            … et {{ category.missingMore }} autre{{ category.missingMore > 1 ? 's' : '' }}
        </p>

        <button
            v-if="hiddenCount > 0"
            type="button"
            data-show-more
            :aria-expanded="String(unfolded)"
            :aria-controls="listId"
            class="mt-auto min-h-11 self-start text-sm font-medium text-accent hover:underline lg:min-h-0"
            @click="unfolded = !unfolded"
        >{{ unfolded ? 'Voir moins' : `Voir les ${hiddenCount} autre${hiddenCount > 1 ? 's' : ''}` }}</button>
    </Card>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { useWowColor } from '../../composables/useWowColor';
import { doersLabel, todoColor } from '../../utils/accountTodo';
import Card from '../ui/Card.vue';
import ProgressBar from '../ui/ProgressBar.vue';

const FOLDED_ITEMS = 5;

const props = defineProps({
    category: { type: Object, required: true },
    subtitle: { type: String, required: true },
    roster: { type: Array, required: true },
});

const { readable } = useWowColor();
const listId = useId();
const unfolded = ref(false);

const color = computed(() => readable(todoColor(props.category.dimension)));
const shownItems = computed(() => (unfolded.value ? props.category.missingItems : props.category.missingItems.slice(0, FOLDED_ITEMS)));
const hiddenCount = computed(() => Math.max(0, props.category.missingItems.length - FOLDED_ITEMS));
</script>
