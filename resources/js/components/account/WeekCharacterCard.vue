<template>
    <Card as="li" data-week-character class="flex flex-col gap-4 p-4">
        <div>
            <h3 class="text-base font-semibold" :style="nameStyle">{{ character.name }}</h3>
            <p class="text-xs text-subtle">{{ character.realm }} · {{ $t('account.week.level', { level: character.level }) }}</p>
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
                >{{ slotLabel(vault.key, entry, weekLabels) }}</li>
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
                {{ unfolded ? $t('account.week.hide_remaining') : $t('account.week.show_remaining') }}
                <Icon :icon="ChevronDown" size="sm" class="transition-transform duration-fast" :class="{ 'rotate-180': unfolded }" />
            </button>

            <div v-if="unfolded" :id="remainingId" data-remaining class="space-y-3 border-t border-default pt-3 text-sm">
                <section v-for="raid in character.raid.remaining" :key="raid.instanceId" class="space-y-1">
                    <h4 class="font-semibold text-default">{{ raid.name }}</h4>
                    <p v-for="difficulty in hardestFirst(raid.difficulties)" :key="difficulty.difficulty" data-difficulty class="text-muted">
                        <span class="font-medium text-default">{{ difficultyLabel(difficulty.difficulty, weekLabels) }}</span>{{ $t('account.week.colon') }}{{ difficulty.encounters.join(', ') }}
                    </p>
                </section>
                <p v-if="character.mythicPlus.missingDungeons.length" data-missing-dungeons class="text-muted">
                    <span class="font-medium text-default">{{ $t('account.week.missing_dungeons') }}</span>{{ $t('account.week.colon') }}{{ character.mythicPlus.missingDungeons.join(', ') }}
                </p>
            </div>
        </template>
    </Card>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { useWowColor } from '../../composables/useWowColor';
import { difficultyLabel, hardestFirst, nextSlot, slotLabel } from '../../utils/accountWeek';
import { useTranslate, useTranslateChoice } from '../../composables/useTranslate';
import { classColor } from '../../utils/wowColors';
import Card from '../ui/Card.vue';
import Icon from '../ui/Icon.vue';


const props = defineProps({
    character: { type: Object, required: true },
});

const { readable, safe } = useWowColor();
const t = useTranslate();
const tChoice = useTranslateChoice();
const weekLabels = {
    difficulty: (difficulty) => t(`raid.difficulty.${difficulty.toLowerCase()}`),
    units: {
        raid: (count) => tChoice('account.week.units.raid', count, { count }),
        mythicPlus: (count) => tChoice('account.week.units.mythic_plus', count, { count }),
    },
};
const remainingId = useId();
const unfolded = ref(false);

const nameStyle = computed(() => {
    const color = safe(classColor, props.character.classId);

    return color ? { color: readable(color) } : undefined;
});

const vaults = computed(() => [
    { key: 'raid', label: t('account.week.raid'), progress: props.character.raid },
    { key: 'mythicPlus', label: t('sheet.sections.mythic_plus'), progress: props.character.mythicPlus },
]);

const hasRemaining = computed(() => props.character.raid.remaining.length > 0 || props.character.mythicPlus.missingDungeons.length > 0);

const DONE_KEYS = Object.freeze({ raid: 'account.week.done.raid', mythicPlus: 'account.week.done.mythic_plus' });

function summaryOf(vault) {
    const { count } = vault.progress;
    const done = tChoice(DONE_KEYS[vault.key], count, { count });
    const next = nextSlot(vault.progress);

    return next
        ? t('account.week.next_slot', { done, missing: next.missing, position: tChoice('account.week.ordinal', next.position, { count: next.position }) })
        : t('account.week.vault_full', { done });
}
</script>
