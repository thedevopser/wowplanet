<template>
    <Card class="space-y-4 p-5 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-semibold text-default">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <div class="text-right">
                <p data-percent class="text-3xl font-bold tabular-nums" :style="{ color: readable(color) }">{{ percent }} %</p>
                <p class="text-sm tabular-nums text-subtle">{{ completed }} / {{ total }}</p>
            </div>
        </div>
        <ProgressBar :value="completed" :max="Math.max(total, 1)" :color="color?.base" :aria-label="`${title} : ${completed} sur ${total}`" />
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import { dimensionColor } from '../../utils/wowColors';
import { useWowColor } from '../../composables/useWowColor';
import Card from '../ui/Card.vue';
import ProgressBar from '../ui/ProgressBar.vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    completed: { type: Number, required: true },
    total: { type: Number, required: true },
    dimension: { type: String, required: true },
});

const { readable, safe } = useWowColor();

const color = computed(() => safe(dimensionColor, props.dimension));
const percent = computed(() => (props.total > 0 ? Math.round((props.completed / props.total) * 100) : 0));
</script>
