<template>
    <div :class="sizeClass" class="relative shrink-0 overflow-hidden rounded-ui-sm border border-default">
        <img
            v-if="src && !errored"
            :src="src"
            :alt="alt"
            :class="sizeClass"
            class="object-cover"
            loading="lazy"
            @error="errored = true"
        />
        <div
            v-else
            :class="[sizeClass, fallbackTextClass]"
            class="flex items-center justify-center bg-surface-raised font-semibold text-muted"
        >{{ fallback }}</div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    fallback: { type: String, default: '?' },
    size: { type: String, default: 'sm' },
});

const errored = ref(false);

const sizeClass = props.size === 'lg' ? 'size-10' : 'size-8';
const fallbackTextClass = props.size === 'lg' ? 'text-sm' : 'text-xs';
</script>
