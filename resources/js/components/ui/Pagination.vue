<template>
    <nav v-if="pageCount > 1" :aria-label="label">
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                <button type="button" aria-label="Page précédente" :class="[BUTTON, IDLE]" :disabled="page <= 1" @click="go(page - 1)">
                    <Icon :icon="ChevronLeft" />
                </button>
            </li>
            <li v-for="(entry, index) in entries" :key="index">
                <span v-if="entry === GAP" aria-hidden="true" class="inline-flex size-11 items-center justify-center text-subtle">…</span>
                <button
                    v-else
                    type="button"
                    :aria-label="`Page ${entry}`"
                    :aria-current="entry === page ? 'page' : undefined"
                    :class="[BUTTON, entry === page ? CURRENT : IDLE]"
                    @click="go(entry)"
                >{{ entry }}</button>
            </li>
            <li>
                <button type="button" aria-label="Page suivante" :class="[BUTTON, IDLE]" :disabled="page >= pageCount" @click="go(page + 1)">
                    <Icon :icon="ChevronRight" />
                </button>
            </li>
        </ul>
    </nav>
</template>

<script>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const GAP = 'gap';
const SHORT_LIST = 7;

const BUTTON = 'inline-flex size-11 items-center justify-center rounded-ui-md text-sm font-medium tabular-nums '
    + 'transition-colors duration-fast focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent '
    + 'disabled:cursor-not-allowed disabled:opacity-40';
const IDLE = 'text-muted hover:bg-surface-raised hover:text-default';
const CURRENT = 'bg-accent text-on-accent';

// First, last, and the neighbours of the current page; gaps stand for the rest.
function pageEntries(page, pageCount) {
    if (pageCount <= SHORT_LIST) {
        return Array.from({ length: pageCount }, (_, index) => index + 1);
    }

    const around = [page - 1, page, page + 1].filter((entry) => entry > 1 && entry < pageCount);
    const entries = [1];
    if (around[0] > 2) {
        entries.push(GAP);
    }
    entries.push(...around);
    if (around.at(-1) < pageCount - 1) {
        entries.push(GAP);
    }
    entries.push(pageCount);

    return entries;
}
</script>

<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    page: { type: Number, required: true },
    pageCount: { type: Number, required: true },
    label: { type: String, required: true },
});

const emit = defineEmits(['update:page']);

const entries = computed(() => pageEntries(props.page, props.pageCount));

function go(page) {
    if (page >= 1 && page <= props.pageCount && page !== props.page) {
        emit('update:page', page);
    }
}
</script>
