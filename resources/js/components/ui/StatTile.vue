<template>
    <div
        class="rounded-ui-md border border-default bg-surface p-4"
        :class="{ 'border-t-4': ruleColor }"
        :style="ruleColor ? { borderTopColor: ruleColor } : undefined"
    >
        <dl>
            <dt class="text-sm font-medium text-muted">{{ label }}</dt>
            <dd class="mt-1">
                <span
                    data-stat-value
                    class="text-2xl font-bold tabular-nums text-default"
                    :style="valueColor ? { color: valueColor } : undefined"
                >
                    {{ displayed }}
                </span>
                <span
                    v-if="trend"
                    data-stat-delta
                    class="mt-1 flex items-center gap-1 text-sm tabular-nums"
                    :class="trend.tone"
                >
                    <Icon :icon="trend.icon" size="sm" />
                    <span class="sr-only">{{ trend.spoken }}</span>
                    {{ formatSigned(delta) }}
                </span>
            </dd>
        </dl>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next';
import Icon from './Icon.vue';
import { formatSigned } from '../../utils/importHistory';

const TRENDS = Object.freeze({
    up: { icon: TrendingUp, tone: 'text-success', spoken: 'en hausse de' },
    down: { icon: TrendingDown, tone: 'text-danger', spoken: 'en baisse de' },
    flat: { icon: Minus, tone: 'text-muted', spoken: 'sans changement,' },
});

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    suffix: { type: String, default: '' },
    delta: { type: Number, default: null },
    // A game colour: the readable variant for the value, the base one for the rule.
    valueColor: { type: String, default: '' },
    ruleColor: { type: String, default: '' },
});

// A narrow no-break space before the unit, as French typography wants it.
const displayed = computed(() => (props.suffix ? `${props.value}\u202f${props.suffix}` : String(props.value)));

const trend = computed(() => {
    if (props.delta === null) {
        return null;
    }

    return props.delta > 0 ? TRENDS.up : props.delta < 0 ? TRENDS.down : TRENDS.flat;
});
</script>
