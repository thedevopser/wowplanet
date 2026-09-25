<template>
    <div class="space-y-6">
        <h2>
            <button
                type="button"
                :aria-expanded="String(expanded)"
                :aria-controls="panelId"
                class="flex w-full items-center justify-between gap-4 rounded-ui-md border border-default bg-surface p-5 text-left
                    transition-colors duration-fast hover:bg-surface-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:p-6"
                @click="expanded = !expanded"
            >
                <span class="font-display text-2xl font-semibold text-default">Équipement</span>
                <span class="flex items-center gap-4">
                    <span class="text-right">
                        <span class="block text-3xl font-bold tabular-nums text-accent">{{ character.ilvl }}</span>
                        <span class="block text-xs font-normal text-subtle">niveau d’objet équipé</span>
                    </span>
                    <Icon :icon="ChevronDown" class="text-subtle transition-transform duration-fast" :class="{ 'rotate-180': expanded }" />
                </span>
            </button>
        </h2>

        <div v-if="expanded" :id="panelId">
            <div class="hidden gap-4 md:grid md:grid-cols-3">
                <ul class="space-y-2">
                    <li v-for="slot in LEFT_SLOTS" :key="slot"><EquipmentSlot :label="SLOT_NAMES[slot]" :item="itemBySlot(slot)" /></li>
                </ul>
                <div class="flex flex-col items-center justify-center">
                    <div class="aspect-3/4 w-full max-w-48 overflow-hidden rounded-ui-md border border-default bg-surface-raised">
                        <img v-if="character.avatarUrl" :src="character.avatarUrl" alt="" class="size-full object-cover">
                    </div>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-accent">{{ character.ilvl }}</p>
                    <p class="text-xs text-subtle">niveau d’objet équipé</p>
                </div>
                <ul class="space-y-2">
                    <li v-for="slot in RIGHT_SLOTS" :key="slot"><EquipmentSlot :label="SLOT_NAMES[slot]" :item="itemBySlot(slot)" /></li>
                </ul>
                <ul class="col-span-3 grid grid-cols-2 gap-4">
                    <li v-for="slot in WEAPON_SLOTS" :key="slot"><EquipmentSlot :label="SLOT_NAMES[slot]" :item="itemBySlot(slot)" /></li>
                </ul>
            </div>

            <ul class="space-y-2 md:hidden">
                <li v-for="slot in ALL_SLOTS" :key="slot"><EquipmentSlot :label="SLOT_NAMES[slot]" :item="itemBySlot(slot)" /></li>
            </ul>
        </div>

        <TalentTreeSection :realm="character.realm" :name="character.name" />
    </div>
</template>

<script>
const SLOT_NAMES = Object.freeze({
    HEAD: 'Tête',
    NECK: 'Cou',
    SHOULDER: 'Épaule',
    BACK: 'Dos',
    CHEST: 'Torse',
    SHIRT: 'Chemise',
    TABARD: 'Tabard',
    WRIST: 'Poignets',
    HANDS: 'Mains',
    WAIST: 'Ceinture',
    LEGS: 'Jambes',
    FEET: 'Pieds',
    FINGER_1: 'Anneau 1',
    FINGER_2: 'Anneau 2',
    TRINKET_1: 'Bijou 1',
    TRINKET_2: 'Bijou 2',
    MAIN_HAND: 'Main droite',
    OFF_HAND: 'Main gauche',
});

const LEFT_SLOTS = Object.freeze(['HEAD', 'NECK', 'SHOULDER', 'BACK', 'CHEST', 'SHIRT', 'TABARD', 'WRIST']);
const RIGHT_SLOTS = Object.freeze(['HANDS', 'WAIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2', 'TRINKET_1', 'TRINKET_2']);
const WEAPON_SLOTS = Object.freeze(['MAIN_HAND', 'OFF_HAND']);
const ALL_SLOTS = Object.freeze([...LEFT_SLOTS, ...RIGHT_SLOTS, ...WEAPON_SLOTS]);
</script>

<script setup>
import { computed, ref, useId } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { useWowheadTooltips } from '../composables/useWowheadTooltips';
import Icon from './ui/Icon.vue';
import EquipmentSlot from './sheet/EquipmentSlot.vue';
import TalentTreeSection from './TalentTreeSection.vue';

useWowheadTooltips();

const props = defineProps({
    character: { type: Object, required: true },
});

const panelId = useId();
const expanded = ref(true);

const equipment = computed(() => new Map((props.character.equipment ?? []).map((item) => [item.slot, item])));

const itemBySlot = (slot) => equipment.value.get(slot) ?? null;
</script>
