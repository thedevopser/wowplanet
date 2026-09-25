<template>
    <p class="flex items-center gap-1 text-xs font-medium" :class="timed ? 'text-success' : 'text-warning'">
        <Icon :icon="timed ? CircleCheck : Hourglass" size="sm" />
        {{ timed ? 'Dans les temps' : 'Hors temps' }}
    </p>
</template>

<script setup>
import { CircleCheck, Hourglass } from 'lucide-vue-next';
import Icon from '../ui/Icon.vue';

// Whether a run beat its timer, in words and not by colour alone.
defineProps({
    timed: { type: Boolean, required: true },
});
</script>
