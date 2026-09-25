<template>
    <div role="status" aria-busy="true" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <span class="sr-only">{{ label }}</span>
        <div
            v-for="card in count"
            :key="card"
            data-skeleton-card
            class="flex items-center gap-4 rounded-ui-md border border-default bg-surface p-4"
        >
            <Skeleton shape="circle" class="size-12 shrink-0" />
            <div class="flex-1 space-y-2">
                <Skeleton shape="text" class="w-2/3" />
                <Skeleton shape="text" class="w-1/2" />
            </div>
        </div>
    </div>
</template>

<script setup>
import Skeleton from './ui/Skeleton.vue';

defineProps({
    label: { type: String, required: true },
    count: { type: Number, default: 6, validator: (value) => Number.isInteger(value) && value >= 1 },
});
</script>
