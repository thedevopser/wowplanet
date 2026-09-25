<template>
    <section :aria-labelledby="titleId" class="space-y-8">
        <Card class="p-5 sm:p-8">
            <div class="text-center">
                <h2 :id="titleId" class="font-display text-2xl font-semibold text-default">{{ title }}</h2>
                <p class="mt-1 text-sm text-muted">
                    <template v-if="subtitle">{{ subtitle }} · </template>
                    Progression globale sur {{ scored.length }} dimension{{ scored.length > 1 ? 's' : '' }}
                    · <a href="/faq" class="underline decoration-dotted hover:text-default">formule v{{ score.version }}</a>
                </p>
            </div>

            <div class="mt-6 flex flex-col items-center justify-center gap-6 sm:flex-row sm:gap-10">
                <ScoreRadar :axes="radarAxes" :size="280" :colors="radarColors" />
                <div class="flex flex-col items-center gap-2">
                    <p class="flex items-baseline gap-2">
                        <span data-global-score class="text-5xl font-bold tabular-nums text-default">{{ formatScore(score.global) }}</span>
                        <span class="text-base text-subtle">/ 100</span>
                    </p>
                    <Badge v-if="rankIsKnown" data-rank tone="rank" :value="score.rank">{{ score.rank }}</Badge>
                    <Badge v-else data-rank>{{ score.rank }}</Badge>
                    <Button class="mt-3" @click="shareOpen = true">
                        <Icon :icon="Share2" size="sm" />
                        Partager ce score
                    </Button>
                </div>
            </div>
        </Card>

        <section class="space-y-4">
            <SectionHeader title="Détail par dimension" :level="3" />
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Card
                    v-for="dim in score.dimensions"
                    :key="dim.key"
                    as="li"
                    :variant="dimensionLinks[dim.key] ? 'interactive' : 'flat'"
                    :data-dimension="dim.key"
                    class="space-y-3 p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <a
                            v-if="dimensionLinks[dim.key]"
                            :href="dimensionLinks[dim.key]"
                            class="text-sm font-semibold text-default outline-none after:absolute after:inset-0"
                            @click="onDimensionClick($event, dim.key)"
                        >{{ dim.label }}</a>
                        <span v-else class="text-sm font-semibold text-default">{{ dim.label }}</span>
                        <span
                            v-if="dim.applicable"
                            class="text-lg font-bold tabular-nums"
                            :style="{ color: readable(colorOf(dim.key)) }"
                        >{{ Math.round(dim.score) }} %</span>
                        <span v-else class="text-xs font-medium text-subtle">Non applicable</span>
                    </div>
                    <ProgressBar
                        :value="dim.applicable ? dim.score : 0"
                        :aria-label="`${dim.label} : ${Math.round(dim.score)} %`"
                        :color="colorOf(dim.key)?.base"
                    />
                    <p class="text-xs tabular-nums text-subtle">
                        {{ formatNumber(dim.completed) }} / {{ formatNumber(dim.total) }}
                    </p>
                </Card>
            </ul>
        </section>

        <section v-if="recommendations.length" class="space-y-4">
            <SectionHeader
                title="Il vous reste…"
                :level="3"
                description="Les catégories les plus proches de la complétion. Dépliez-en une pour voir ce qu’il manque."
            />
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="(rec, index) in recommendations" :key="rec.key" as="li" data-recommendation class="overflow-hidden">
                    <button
                        type="button"
                        :aria-expanded="String(expanded === rec.key)"
                        :aria-controls="`${titleId}-rec-${index}`"
                        class="flex min-h-11 w-full items-center gap-3 p-4 text-left transition-colors duration-fast hover:bg-surface-raised
                            focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
                        @click="expanded = expanded === rec.key ? null : rec.key"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-ui-sm bg-surface-raised text-sm font-bold tabular-nums"
                            :style="{ color: readable(colorOf(rec.dimensionKey)) }"
                        >{{ rec.missing }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-default">{{ rec.name }}</span>
                            <span class="block text-xs tabular-nums text-subtle">{{ rec.completed }} / {{ rec.total }} — {{ rec.dimension }}</span>
                        </span>
                        <Icon :icon="ChevronDown" size="sm" class="shrink-0 text-subtle transition-transform duration-fast" :class="{ 'rotate-180': expanded === rec.key }" />
                    </button>
                    <div v-if="expanded === rec.key" :id="`${titleId}-rec-${index}`" class="border-t border-default px-4 pb-4">
                        <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                            <li v-for="item in rec.missingItems" :key="item.id ?? item.name" class="text-sm">
                                <a :href="item.wowheadUrl" target="_blank" rel="noopener" class="block truncate py-1 text-muted hover:text-default hover:underline">{{ item.name }}</a>
                            </li>
                        </ul>
                        <p v-if="rec.missingMore > 0" class="pt-1 text-xs text-subtle">
                            … et {{ rec.missingMore }} autre{{ rec.missingMore > 1 ? 's' : '' }}
                        </p>
                    </div>
                </Card>
            </ul>
        </section>

        <ShareScoreModal :show="shareOpen" :variant="shareVariant" :score-data="shareData" @close="shareOpen = false" />
    </section>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { ChevronDown, Share2 } from 'lucide-vue-next';
import { formatScore } from '../utils/formatScore';
import { dimensionColor, rankColor } from '../utils/wowColors';
import { useWowColor } from '../composables/useWowColor';
import Badge from './ui/Badge.vue';
import Button from './ui/Button.vue';
import Card from './ui/Card.vue';
import Icon from './ui/Icon.vue';
import ProgressBar from './ui/ProgressBar.vue';
import SectionHeader from './ui/SectionHeader.vue';
import ScoreRadar from './ScoreRadar.vue';
import ShareScoreModal from './ShareScoreModal.vue';

const props = defineProps({
    score: { type: Object, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    recommendations: { type: Array, default: () => [] },
    dimensionLinks: { type: Object, default: () => ({}) },
    shareData: { type: Object, default: () => ({}) },
    shareVariant: { type: String, default: 'personal' },
});

const emit = defineEmits(['navigate']);

const titleId = useId();
const expanded = ref(null);
const shareOpen = ref(false);
const { readable, safe } = useWowColor();

// Dimensions without data leave the radar: an axis at zero would warp the polygon.
const scored = computed(() => (props.score.dimensions ?? []).filter((dimension) => dimension.applicable));
const radarAxes = computed(() => scored.value.map((dimension) => ({ label: dimension.label, score: dimension.score })));
const radarColors = computed(() => scored.value.map((dimension) => colorOf(dimension.key)?.base ?? 'currentColor'));
const rankIsKnown = computed(() => safe(rankColor, props.score.rank) !== null);

const colorOf = (key) => safe(dimensionColor, key);
const formatNumber = (value) => Number(value).toLocaleString('fr-FR');

// A plain click moves inside the page; a modified one (new tab) is left to the browser.
function onDimensionClick(event, key) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    emit('navigate', key);
}
</script>
