<template>
    <span aria-hidden="true" class="block bg-surface-raised motion-safe:animate-pulse" :class="SHAPES[shape] ?? SHAPES.block" />
</template>

<script>
// The caller gives the size (h-24 w-full…): the skeleton holds the room of the coming content.
const SHAPES = Object.freeze({
    block: 'rounded-ui-md',
    text: 'h-4 rounded-ui-sm',
    circle: 'rounded-full',
});
</script>

<script setup>
defineProps({
    shape: { type: String, default: 'block', validator: (value) => Object.hasOwn(SHAPES, value) },
});
</script>
