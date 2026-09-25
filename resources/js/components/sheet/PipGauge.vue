<template>
    <span role="img" :aria-label="label" class="flex gap-1">
        <span
            v-for="index in total"
            :key="index"
            :data-pip="index <= filled ? 'filled' : 'empty'"
            class="h-2 min-w-2 flex-1 rounded-full"
            :class="{ 'bg-surface-raised ring-1 ring-inset ring-(--wp-border)': index > filled }"
            :style="index <= filled ? { backgroundColor: color } : undefined"
        />
    </span>
</template>

<script setup>
// One pip per step (a boss, a key level): progress read at a glance, and counted exactly.
defineProps({
    filled: { type: Number, required: true },
    total: { type: Number, required: true },
    color: { type: String, required: true },
    label: { type: String, required: true },
});
</script>
