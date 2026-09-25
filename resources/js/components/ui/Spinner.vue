<template>
    <span role="status" class="inline-flex items-center text-muted">
        <Icon :icon="LoaderCircle" :size="size" class="motion-safe:animate-spin" />
        <span class="sr-only">{{ label }}</span>
    </span>
</template>

<script setup>
import { LoaderCircle } from 'lucide-vue-next';
import Icon, { ICON_SIZES } from './Icon.vue';

defineProps({
    size: { type: String, default: 'md', validator: (value) => Object.hasOwn(ICON_SIZES, value) },
    label: { type: String, default: 'Chargement…' },
});
</script>
