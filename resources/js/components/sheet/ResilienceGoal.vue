<template>
    <Card as="section" data-resilience-goal class="space-y-4 p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-default">Objectif rési</h3>
            <Select v-model="target" label="Niveau visé" :options="options" />
        </div>

        <p v-if="!remaining.length" data-goal-reached class="flex items-center gap-2 text-sm font-medium text-success">
            <Icon :icon="CircleCheck" size="sm" class="shrink-0" />
            <span>Rési +{{ target }} atteinte : les {{ resilience.dungeons.length }} donjons sont timés à ce niveau ou au-dessus.</span>
        </p>

        <template v-else>
            <p data-goal-count class="text-sm text-muted">{{ countLabel(remaining.length) }} sur {{ resilience.dungeons.length }}</p>
            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <li
                    v-for="dungeon in remaining"
                    :key="dungeon.dungeon_id"
                    data-remaining
                    class="rounded-ui-md bg-surface-raised px-3 py-2"
                >
                    <p class="truncate text-sm font-semibold text-default">{{ dungeon.name }}</p>
                    <p class="text-xs text-muted">
                        <template v-if="dungeon.best_timed_level === null">Jamais timé</template>
                        <template v-else>Meilleure clé timée : <span class="font-semibold tabular-nums text-default">+{{ dungeon.best_timed_level }}</span></template>
                    </p>
                </li>
            </ul>
        </template>
    </Card>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { CircleCheck } from 'lucide-vue-next';
import { defaultResilienceTarget, remainingDungeons } from '../../utils/mythicRuns';
import Card from '../ui/Card.vue';
import Icon from '../ui/Icon.vue';
import Select from '../ui/Select.vue';

const props = defineProps({
    resilience: { type: Object, required: true },
});

// The Select primitive works on strings.
const target = ref(String(defaultResilienceTarget(props.resilience)));

watch(() => props.resilience, (resilience) => {
    target.value = String(defaultResilienceTarget(resilience));
});

const remaining = computed(() => remainingDungeons(props.resilience, Number(target.value)));

const countLabel = (count) => (count === 1 ? '1 donjon restant' : `${count} donjons restants`);

function hintFor(count) {
    if (count === 0) {
        return 'Atteinte';
    }

    return count === 1 ? '1 restant' : `${count} restants`;
}

const options = computed(() => props.resilience.targets.map(({ level, remaining: left }) => ({
    value: String(level),
    label: `+${level}`,
    hint: hintFor(left.length),
})));
</script>
