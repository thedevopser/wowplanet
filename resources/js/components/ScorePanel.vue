<template>
    <section :aria-labelledby="titleId" class="space-y-8">
        <Card class="p-5 sm:p-8">
            <div class="text-center">
                <h2 :id="titleId" class="font-display text-2xl font-semibold text-default">{{ title }}</h2>
                <p class="mt-1 text-sm text-muted">
                    <template v-if="subtitle">{{ subtitle }} · </template>
                    {{ $tChoice('score.overall', scored.length, { count: scored.length }) }}
                    · <a :href="localizedUrl('/faq')" class="underline decoration-dotted hover:text-default">{{ $t('score.formula', { version: score.version }) }}</a>
                </p>
            </div>

            <div class="mt-6 flex flex-col items-center justify-center gap-6 sm:flex-row sm:gap-10">
                <ScoreRadar :axes="radarAxes" :size="280" :colors="radarColors" />
                <div class="flex flex-col items-center gap-2">
                    <p class="flex items-baseline gap-2">
                        <span data-global-score class="text-5xl font-bold tabular-nums text-default">{{ formatScore(score.global) }}</span>
                        <span class="text-base text-subtle">/ 100</span>
                    </p>
                    <Badge v-if="rankIsKnown" data-rank tone="rank" :value="score.rank">{{ rankLabel(score.rank) }}</Badge>
                    <Badge v-else data-rank>{{ rankLabel(score.rank) }}</Badge>
                    <Button class="mt-3" @click="shareOpen = true">
                        <Icon :icon="Share2" size="sm" />
                        {{ $t('score.share') }}
                    </Button>
                </div>
            </div>
        </Card>

        <section class="space-y-4">
            <SectionHeader :title="$t('score.by_dimension')" :level="3" />
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
                        >{{ dimensionLabel(dim.key) }}</a>
                        <span v-else class="text-sm font-semibold text-default">{{ dimensionLabel(dim.key) }}</span>
                        <span
                            v-if="dim.applicable"
                            class="text-lg font-bold tabular-nums"
                            :style="{ color: readable(colorOf(dim.key)) }"
                        >{{ Math.round(dim.score) }} %</span>
                        <span v-else class="text-xs font-medium text-subtle">{{ $t('score.not_applicable') }}</span>
                    </div>
                    <ProgressBar
                        :value="dim.applicable ? dim.score : 0"
                        :aria-label="$t('score.dimension_progress', { dimension: dimensionLabel(dim.key), score: Math.round(dim.score) })"
                        :color="colorOf(dim.key)?.base"
                    />
                    <p class="text-xs tabular-nums text-subtle">
                        {{ formatNumber(dim.completed) }} / {{ formatNumber(dim.total) }}
                    </p>
                </Card>
            </ul>
        </section>

        <Card v-if="todoUrl" variant="interactive" data-todo-link class="flex items-center gap-4 p-4 sm:p-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-ui-sm bg-surface-raised text-accent">
                <Icon :icon="ListChecks" />
            </span>
            <span class="min-w-0 flex-1">
                <Link
                    :href="todoUrl"
                    class="text-base font-semibold text-default outline-none after:absolute after:inset-0
                        focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-accent"
                >{{ $t('score.todo.title') }}</Link>
                <span class="block text-sm text-muted">{{ $t('score.todo.description') }}</span>
            </span>
            <Icon :icon="ChevronRight" size="sm" class="shrink-0 text-subtle" />
        </Card>

        <ShareScoreModal :show="shareOpen" :variant="shareVariant" :score-data="shareData" @close="shareOpen = false" />
    </section>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { ChevronRight, ListChecks, Share2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dimensionColor, rankColor } from '../utils/wowColors';
import { useWowColor } from '../composables/useWowColor';
import { useLocaleFormat } from '../composables/useLocaleFormat';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';
import { useScoreLabels } from '../composables/useScoreLabels';
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
    todoUrl: { type: String, default: '' },
    dimensionLinks: { type: Object, default: () => ({}) },
    shareData: { type: Object, default: () => ({}) },
    shareVariant: { type: String, default: 'personal' },
});

const emit = defineEmits(['navigate']);

const titleId = useId();
const shareOpen = ref(false);
const { readable, safe } = useWowColor();
const { formatNumber, formatScore } = useLocaleFormat();
const { localizedUrl } = useLocalizedUrl();
const { rankLabel, dimensionLabel } = useScoreLabels();

// Dimensions without data leave the radar: an axis at zero would warp the polygon.
const scored = computed(() => (props.score.dimensions ?? []).filter((dimension) => dimension.applicable));
const radarAxes = computed(() => scored.value.map((dimension) => ({ label: dimensionLabel(dimension.key), score: dimension.score })));
const radarColors = computed(() => scored.value.map((dimension) => colorOf(dimension.key)?.base ?? 'currentColor'));
const rankIsKnown = computed(() => safe(rankColor, props.score.rank) !== null);

const colorOf = (key) => safe(dimensionColor, key);

// A plain click moves inside the page; a modified one (new tab) is left to the browser.
function onDimensionClick(event, key) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    emit('navigate', key);
}
</script>
