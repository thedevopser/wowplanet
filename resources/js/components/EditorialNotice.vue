<template>
    <div role="note" class="rounded-ui-md border p-4 text-sm leading-relaxed text-default" :class="TONES[tone]">
        <slot />
    </div>
</template>

<script>
const TONES = Object.freeze({
    warning: 'border-warning/40 bg-warning/10',
    info: 'border-info/40 bg-info/10',
});
</script>

<script setup>
defineProps({
    tone: { type: String, default: 'warning', validator: (value) => Object.hasOwn(TONES, value) },
});
</script>
