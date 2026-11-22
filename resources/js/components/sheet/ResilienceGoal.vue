<template>
    <Card as="section" data-resilience-goal class="space-y-4 p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-default">{{ $t('sheet.resilience.title') }}</h3>
            <Select v-model="target" :label="$t('sheet.resilience.target')" :options="options" />
        </div>

        <p v-if="!remaining.length" data-goal-reached class="flex items-center gap-2 text-sm font-medium text-success">
            <Icon :icon="CircleCheck" size="sm" class="shrink-0" />
            <span>{{ $t('sheet.resilience.reached', { level: target, count: resilience.dungeons.length }) }}</span>
        </p>

        <template v-else>
            <p data-goal-count class="text-sm text-muted">{{ $tChoice('sheet.resilience.remaining_of', remaining.length, { count: remaining.length, total: resilience.dungeons.length }) }}</p>
            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <li
                    v-for="dungeon in remaining"
                    :key="dungeon.dungeon_id"
                    data-remaining
                    class="rounded-ui-md bg-surface-raised px-3 py-2"
                >
                    <p class="truncate text-sm font-semibold text-default">{{ dungeon.name }}</p>
                    <p class="text-xs text-muted">
                        <template v-if="dungeon.best_timed_level === null">{{ $t('sheet.resilience.never_timed') }}</template>
                        <template v-else>{{ $t('sheet.resilience.best_timed') }} <span class="font-semibold tabular-nums text-default">+{{ dungeon.best_timed_level }}</span></template>
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
import { useTranslate, useTranslateChoice } from '../../composables/useTranslate';

const props = defineProps({
    resilience: { type: Object, required: true },
});

// The Select primitive works on strings.
const target = ref(String(defaultResilienceTarget(props.resilience)));

watch(() => props.resilience, (resilience) => {
    target.value = String(defaultResilienceTarget(resilience));
});

const remaining = computed(() => remainingDungeons(props.resilience, Number(target.value)));

const t = useTranslate();
const tChoice = useTranslateChoice();

function hintFor(count) {
    if (count === 0) {
        return t('sheet.resilience.goal_reached');
    }

    return tChoice('sheet.resilience.remaining', count, { count });
}

const options = computed(() => props.resilience.targets.map(({ level, remaining: left }) => ({
    value: String(level),
    label: `+${level}`,
    hint: hintFor(left.length),
})));
</script>
