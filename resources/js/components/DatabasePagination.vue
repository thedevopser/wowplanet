<template>
    <div v-if="lastPage > 1" class="flex flex-col items-center justify-between gap-3 sm:flex-row" :class="isTop ? '' : 'pt-4'">
        <p :aria-live="isTop ? undefined : 'polite'" class="text-sm tabular-nums text-muted">
            {{ $t('database.pagination.summary', { total: total.toLocaleString(locale), page: String(currentPage), last: String(lastPage) }) }}
        </p>
        <Pagination
            :page="currentPage"
            :page-count="lastPage"
            :label="`${label ?? $t('database.pagination.label')} (${isTop ? $t('database.pagination.top') : $t('database.pagination.bottom')})`"
            @update:page="$emit('page-change', $event)"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Pagination from './ui/Pagination.vue';
import { useActiveLocale } from '../composables/useActiveLocale';

const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, default: 0 },
    label: { type: String, default: null },
    placement: { type: String, default: 'bottom', validator: (value) => ['top', 'bottom'].includes(value) },
});

defineEmits(['page-change']);

// The bottom copy alone announces the page, or a screen reader would hear it twice.
const isTop = computed(() => props.placement === 'top');
const locale = useActiveLocale();
</script>
