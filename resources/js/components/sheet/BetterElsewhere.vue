<template>
    <p class="flex items-center gap-1 text-xs text-warning">
        <Icon :icon="Users" size="sm" />
        Meilleur : {{ character }} — {{ detail }}
    </p>
</template>

<script setup>
import { Users } from 'lucide-vue-next';
import Icon from '../ui/Icon.vue';

// Another character of the account reached further: the same wording wherever it shows.
defineProps({
    character: { type: String, required: true },
    detail: { type: String, required: true },
});
</script>
