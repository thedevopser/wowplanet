<template>
    <Select v-model="selected" :label="$t('sheet.expansion')" :options="options" />
</template>

<script>
// The score dimension whose colour a collection type takes.
const DIMENSIONS = Object.freeze({ quests: 'quests', achievements: 'achievements', reputations: 'reputations', recipes: 'professions' });
</script>

<script setup>
import { computed } from 'vue';
import { dimensionColor } from '../../utils/wowColors';
import { useWowColor } from '../../composables/useWowColor';
import { useLocaleFormat } from '../../composables/useLocaleFormat';
import { useTranslate } from '../../composables/useTranslate';
import { UNCATEGORIZED } from '../../utils/collections';

const t = useTranslate();
import Select from '../ui/Select.vue';

const props = defineProps({
    expansions: { type: Array, required: true },
    collections: { type: Object, required: true },
    collectionType: { type: String, required: true, validator: (value) => Object.hasOwn(DIMENSIONS, value) },
    modelValue: { type: Number, required: true },
});

const emit = defineEmits(['update:modelValue']);

const { safe } = useWowColor();

const selected = computed({
    get: () => String(props.modelValue),
    set: (value) => emit('update:modelValue', Number(value)),
});

const color = computed(() => safe(dimensionColor, DIMENSIONS[props.collectionType])?.base);
const { formatNumber } = useLocaleFormat();

// A bucket entry (onlyWhenFilled) is not an expansion: it only shows where it holds something.
const options = computed(() => props.expansions
    .filter((expansion) => !expansion.onlyWhenFilled || (props.collections[expansion.id]?.[props.collectionType]?.total ?? 0) > 0)
    .toReversed()
    .map((expansion) => {
        const data = props.collections[expansion.id]?.[props.collectionType];

        return {
            value: String(expansion.id),
            label: expansion.name === UNCATEGORIZED ? t('sheet.collections.uncategorized_category') : expansion.name,
            ...(data ? { hint: `${formatNumber(data.completed)} / ${formatNumber(data.total)}`, progress: { value: data.completed, max: data.total, color: color.value } } : {}),
        };
    }));
</script>
