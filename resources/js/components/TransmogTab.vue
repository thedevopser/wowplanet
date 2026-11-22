<template>
    <div class="space-y-6">
        <EmptyState v-if="!slots.length" :icon="Shirt" :title="$t('score.dimension.transmog')" :message="$t('sheet.transmog.empty')" />

        <template v-else>
            <Select v-if="categories.length > 1" v-model="category" :label="$t('sheet.collections.category')" :options="categoryOptions" />

            <ProgressSummary
                :title="$t('score.dimension.transmog')"
                :description="$t('sheet.transmog.description')"
                :completed="totals.completed"
                :total="totals.total"
                dimension="transmog"
            />

            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="slot in visibleSlots" :key="slot.slot" as="li" data-slot class="space-y-2 p-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <span data-slot-name class="text-sm font-semibold text-default">{{ slotLabel(slot.slot) }}</span>
                        <span class="text-xs tabular-nums text-subtle">{{ formatNumber(slot.completed) }} / {{ formatNumber(slot.total) }}</span>
                    </div>
                    <ProgressBar
                        :value="slot.completed"
                        :max="Math.max(slot.total, 1)"
                        :color="barColor"
                        :aria-label="$t('common.progress_of', { label: slotLabel(slot.slot), completed: slot.completed, total: slot.total })"
                    />
                </Card>
            </ul>
        </template>
    </div>
</template>

<script>
const ALL = 'tout';

const SLOT_KEY_PREFIX = 'database.slots.';
</script>

<script setup>
import { computed } from 'vue';
import { Shirt } from 'lucide-vue-next';
import { useQueryParam } from '../composables/useQueryParam';
import { useWowColor } from '../composables/useWowColor';
import { useLocaleFormat } from '../composables/useLocaleFormat';
import { useTranslate } from '../composables/useTranslate';
import { dimensionColor } from '../utils/wowColors';
import Card from './ui/Card.vue';
import EmptyState from './ui/EmptyState.vue';
import ProgressBar from './ui/ProgressBar.vue';
import Select from './ui/Select.vue';
import ProgressSummary from './sheet/ProgressSummary.vue';

const props = defineProps({
    character: { type: Object, required: true },
});

const { safe } = useWowColor();

const slots = computed(() => props.character?.appearances ?? []);
const categories = computed(() => [...new Set(slots.value.map((slot) => slot.category).filter(Boolean))].toSorted(compareNames));
const category = useQueryParam('categorie', ALL);

const categoryOptions = computed(() => [{ value: ALL, label: t('sheet.transmog.all') }, ...categories.value.map((name) => ({ value: name, label: name }))]);

const t = useTranslate();
const { formatNumber, compareNames } = useLocaleFormat();

function slotLabel(slot) {
    const label = t(SLOT_KEY_PREFIX + slot);

    return label === SLOT_KEY_PREFIX + slot ? slot : label;
}
const barColor = computed(() => safe(dimensionColor, 'transmog')?.base);

const visibleSlots = computed(() => (category.value === ALL ? slots.value : slots.value.filter((slot) => slot.category === category.value))
    .toSorted((a, b) => compareNames(slotLabel(a.slot), slotLabel(b.slot))));

const totals = computed(() => visibleSlots.value.reduce(
    (sum, slot) => ({ completed: sum.completed + (slot.completed || 0), total: sum.total + (slot.total || 0) }),
    { completed: 0, total: 0 },
));
</script>
