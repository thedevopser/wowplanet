<template>
    <div class="space-y-6">
        <EmptyState v-if="!mythic" :icon="Timer" title="Mythique+" message="Aucune donnée Mythique+ pour la saison en cours." />

        <template v-else>
            <Card class="grid gap-6 p-5 sm:p-6 md:grid-cols-[auto_1fr] md:items-center">
                <div class="flex items-center gap-5">
                    <div>
                        <h2 class="font-display text-2xl font-semibold text-default">Mythique+</h2>
                        <p class="mt-1 text-sm text-muted">Saison {{ mythic.season_id }}</p>
                    </div>
                    <div class="border-l border-default pl-5">
                        <p class="text-xs font-medium text-subtle">Cote</p>
                        <p data-rating class="text-4xl font-bold leading-none tabular-nums" :style="{ color: readable(apiColor(mythic.rating_color)) }">
                            {{ formatNumber(Math.round(mythic.rating)) }}
                        </p>
                    </div>
                </div>
                <dl data-season-stats class="grid grid-cols-3 gap-3 md:justify-self-end">
                    <div v-for="stat in statsList" :key="stat.label" class="rounded-ui-md bg-surface-raised px-3 py-2">
                        <dt class="text-xs text-subtle">{{ stat.label }}</dt>
                        <dd class="text-xl font-bold tabular-nums text-default">{{ stat.value }}</dd>
                    </div>
                </dl>
            </Card>

            <EmptyState v-if="!cards.length" :icon="Timer" title="Aucune course" message="Aucune course enregistrée cette saison." />

            <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card v-for="card in cards" :key="card.dungeon_id" as="li" :data-dungeon="card.dungeon_id" class="flex flex-col">
                    <div data-lead class="flex gap-4 p-4">
                        <p
                            data-key
                            class="flex size-16 shrink-0 items-center justify-center rounded-ui-md border-2 bg-surface-raised text-2xl font-bold tabular-nums"
                            :style="keyStyle(card.lead)"
                        >+{{ card.lead.level }}</p>
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <h3 class="truncate text-base font-semibold text-default">{{ card.name }}</h3>
                            <RunTiming :timed="card.lead.is_timed" />
                            <dl class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <div class="flex gap-1">
                                    <dt class="text-subtle">Score</dt>
                                    <dd class="font-semibold tabular-nums" :style="{ color: readable(apiColor(card.lead.map_score_color)) }">{{ Math.round(card.lead.map_score) }}</dd>
                                </div>
                                <div class="flex gap-1">
                                    <dt class="text-subtle">Durée</dt>
                                    <dd class="tabular-nums text-default">{{ formatRunDuration(card.lead.duration_ms) }}</dd>
                                </div>
                            </dl>
                            <p class="text-xs text-subtle">{{ formatDate(card.lead.completed_at) }}</p>
                        </div>
                    </div>
                    <RunGroup :members="card.lead.members" class="border-t border-default px-4" />

                    <div v-if="card.other" data-other class="mt-auto border-t border-default bg-surface-raised px-4 py-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span class="font-bold tabular-nums" :style="{ color: readable(apiColor(card.other.map_score_color)) }">+{{ card.other.level }}</span>
                            <RunTiming :timed="card.other.is_timed" />
                            <span class="text-subtle">Score <span class="font-semibold tabular-nums text-default">{{ Math.round(card.other.map_score) }}</span></span>
                            <span class="tabular-nums text-subtle">{{ formatRunDuration(card.other.duration_ms) }}</span>
                            <span class="text-xs text-subtle">{{ formatDate(card.other.completed_at) }}</span>
                        </div>
                        <RunGroup :members="card.other.members" />
                    </div>
                </Card>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Timer } from 'lucide-vue-next';
import { useCharacterStore } from '../stores/character';
import { useWowColor } from '../composables/useWowColor';
import { dungeonCards, formatRunDuration, seasonStats } from '../utils/mythicRuns';
import { readableVariants, rgbToHex } from '../utils/wowColors';
import Card from './ui/Card.vue';
import EmptyState from './ui/EmptyState.vue';
import RunGroup from './sheet/RunGroup.vue';
import RunTiming from './sheet/RunTiming.vue';

const store = useCharacterStore();
const { readable, safe } = useWowColor();

const mythic = computed(() => store.character?.mythicKeystone ?? null);
const cards = computed(() => dungeonCards(mythic.value?.best_runs));

const statsList = computed(() => {
    const stats = seasonStats(mythic.value?.best_runs);

    return [
        { label: 'Donjons joués', value: String(stats.dungeons) },
        { label: 'Plus haute clé dans les temps', value: stats.highestTimed === null ? '—' : `+${stats.highestTimed}` },
        { label: 'Donjons dans les temps', value: `${stats.timedDungeons} / ${stats.dungeons}` },
    ];
});

// The API gives its own colours, as { r, g, b }: they go through the readable variants too.
const apiColor = (rgb) => safe((value) => readableVariants(rgbToHex(value)), rgb);

function keyStyle(run) {
    const color = apiColor(run.map_score_color);

    return color ? { color: readable(color), borderColor: color.base } : undefined;
}

const formatNumber = (value) => Number(value).toLocaleString('fr-FR');

function formatDate(timestamp) {
    return timestamp ? new Date(timestamp).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}
</script>
