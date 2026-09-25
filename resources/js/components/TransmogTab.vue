<template>
    <div class="space-y-6">
        <EmptyState v-if="!slots.length" :icon="Shirt" title="Garde-robe" message="Aucune apparence connue pour ce personnage." />

        <template v-else>
            <Select v-if="categories.length > 1" v-model="category" label="Catégorie" :options="categoryOptions" />

            <ProgressSummary
                title="Garde-robe"
                description="Apparences débloquées sur le compte"
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
                        :aria-label="`${slotLabel(slot.slot)} : ${slot.completed} sur ${slot.total}`"
                    />
                </Card>
            </ul>
        </template>
    </div>
</template>

<script>
const ALL = 'tout';

const SLOT_NAMES = Object.freeze({
    HEAD: 'Tête',
    SHOULDER: 'Épaules',
    SHIRT: 'Chemise',
    CHEST: 'Torse',
    WAIST: 'Ceinture',
    LEGS: 'Jambes',
    FEET: 'Pieds',
    WRIST: 'Poignets',
    HAND: 'Mains',
    CLOAK: 'Cape',
    TABARD: 'Tabard',
    WEAPON: 'Arme',
    SHIELD: 'Bouclier',
    RANGED: 'Distance',
    TWOHWEAPON: 'Arme à deux mains',
    WEAPONOFFHAND: 'Arme en main gauche',
    HOLDABLE: 'Tenu en main gauche',
});
</script>

<script setup>
import { computed } from 'vue';
import { Shirt } from 'lucide-vue-next';
import { useQueryParam } from '../composables/useQueryParam';
import { useWowColor } from '../composables/useWowColor';
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
const categories = computed(() => [...new Set(slots.value.map((slot) => slot.category).filter(Boolean))].toSorted((a, b) => a.localeCompare(b, 'fr')));
const category = useQueryParam('categorie', ALL);

const categoryOptions = computed(() => [{ value: ALL, label: 'Tout' }, ...categories.value.map((name) => ({ value: name, label: name }))]);

const slotLabel = (slot) => SLOT_NAMES[slot] ?? slot;
const formatNumber = (value) => Number(value).toLocaleString('fr-FR');
const barColor = computed(() => safe(dimensionColor, 'transmog')?.base);

const visibleSlots = computed(() => (category.value === ALL ? slots.value : slots.value.filter((slot) => slot.category === category.value))
    .toSorted((a, b) => slotLabel(a.slot).localeCompare(slotLabel(b.slot), 'fr')));

const totals = computed(() => visibleSlots.value.reduce(
    (sum, slot) => ({ completed: sum.completed + (slot.completed || 0), total: sum.total + (slot.total || 0) }),
    { completed: 0, total: 0 },
));
</script>
