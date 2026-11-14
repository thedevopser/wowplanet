<template>
    <Card as="li" data-week-character class="flex flex-col gap-4 p-4">
        <div>
            <h3 class="text-base font-semibold" :style="nameStyle">{{ character.name }}</h3>
            <p class="text-xs text-subtle">{{ character.realm }} · niveau {{ character.level }}</p>
        </div>

        <div v-for="vault in vaults" :key="vault.key" :data-vault="vault.key" class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ vault.label }}</p>
            <ul class="grid grid-cols-3 gap-2">
                <li
                    v-for="entry in vault.progress.slots"
                    :key="entry.threshold"
                    data-slot
                    :data-reached="String(entry.reached)"
                    class="flex h-11 items-center justify-center rounded-ui-sm border px-2 text-center text-sm tabular-nums"
                    :class="entry.reached ? 'border-accent bg-accent/10 font-semibold text-default' : 'border-dashed border-default text-subtle'"
                >{{ slotLabel(vault.key, entry) }}</li>
            </ul>
            <p class="text-xs text-muted">{{ summaryOf(vault) }}</p>
        </div>

        <template v-if="hasRemaining">
            <button
                type="button"
                data-show-remaining
                :aria-expanded="String(unfolded)"
                :aria-controls="remainingId"
                class="inline-flex min-h-11 items-center gap-1 self-start text-sm font-medium text-accent hover:underline lg:min-h-0"
                @click="unfolded = !unfolded"
            >
                {{ unfolded ? 'Masquer ce qu’il reste' : 'Voir ce qu’il reste' }}
                <Icon :icon="ChevronDown" size="sm" class="transition-transform duration-fast" :class="{ 'rotate-180': unfolded }" />
            </button>

            <div v-if="unfolded" :id="remainingId" data-remaining class="space-y-3 border-t border-default pt-3 text-sm">
                <section v-for="raid in character.raid.remaining" :key="raid.instanceId" class="space-y-1">
                    <h4 class="font-semibold text-default">{{ raid.name }}</h4>
                    <p v-for="difficulty in hardestFirst(raid.difficulties)" :key="difficulty.difficulty" data-difficulty class="text-muted">
                        <span class="font-medium text-default">{{ difficultyLabel(difficulty.difficulty) }}</span> : {{ difficulty.encounters.join(', ') }}
                    </p>
                </section>
                <p v-if="character.mythicPlus.missingDungeons.length" data-missing-dungeons class="text-muted">
                    <span class="font-medium text-default">Donjons sans clé</span> : {{ character.mythicPlus.missingDungeons.join(', ') }}
                </p>
            </div>
        </template>
    </Card>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { useWowColor } from '../../composables/useWowColor';
import { difficultyLabel, hardestFirst, nextSlot, slotLabel, unitOf } from '../../utils/accountWeek';
import { classColor } from '../../utils/wowColors';
import Card from '../ui/Card.vue';
import Icon from '../ui/Icon.vue';

const NOTHING_DONE = Object.freeze({ raid: 'Aucun boss battu', mythicPlus: 'Aucun donjon fait' });

const DONE = Object.freeze({
    raid: (count) => `${unitOf('raid', count)} battu${count > 1 ? 's' : ''}`,
    mythicPlus: (count) => `${unitOf('mythicPlus', count)} fait${count > 1 ? 's' : ''}`,
});

const props = defineProps({
    character: { type: Object, required: true },
});

const { readable, safe } = useWowColor();
const remainingId = useId();
const unfolded = ref(false);

const nameStyle = computed(() => {
    const color = safe(classColor, props.character.classId);

    return color ? { color: readable(color) } : undefined;
});

const vaults = computed(() => [
    { key: 'raid', label: 'Raid', progress: props.character.raid },
    { key: 'mythicPlus', label: 'Mythique+', progress: props.character.mythicPlus },
]);

const hasRemaining = computed(() => props.character.raid.remaining.length > 0 || props.character.mythicPlus.missingDungeons.length > 0);

const ordinal = (position) => (position === 1 ? '1er' : `${position}e`);

function summaryOf(vault) {
    const { count } = vault.progress;
    const done = count === 0 ? NOTHING_DONE[vault.key] : DONE[vault.key](count);
    const next = nextSlot(vault.progress);

    return next ? `${done} · encore ${next.missing} pour le ${ordinal(next.position)} emplacement` : `${done} · chambre forte remplie`;
}
</script>
