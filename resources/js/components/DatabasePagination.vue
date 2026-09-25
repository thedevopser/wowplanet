<template>
    <div v-if="lastPage > 1" class="flex flex-col items-center justify-between gap-3 sm:flex-row" :class="isTop ? '' : 'pt-4'">
        <p :aria-live="isTop ? undefined : 'polite'" class="text-sm tabular-nums text-muted">
            {{ total.toLocaleString('fr-FR') }} résultats — page {{ currentPage }} / {{ lastPage }}
        </p>
        <Pagination
            :page="currentPage"
            :page-count="lastPage"
            :label="`${label} (${isTop ? 'haut' : 'bas'})`"
            @update:page="$emit('page-change', $event)"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Pagination from './ui/Pagination.vue';

const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, default: 0 },
    label: { type: String, default: 'Pages des résultats' },
    placement: { type: String, default: 'bottom', validator: (value) => ['top', 'bottom'].includes(value) },
});

defineEmits(['page-change']);

// The bottom copy alone announces the page, or a screen reader would hear it twice.
const isTop = computed(() => props.placement === 'top');
</script>
