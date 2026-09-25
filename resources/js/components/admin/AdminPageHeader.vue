<template>
    <header class="flex flex-wrap items-end justify-between gap-4 rounded-ui-md border border-l-4 border-default border-l-accent bg-surface p-5 sm:p-6">
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-bold text-default sm:text-3xl">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-muted sm:text-base">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" data-header-actions class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>
