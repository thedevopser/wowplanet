<template>
    <div class="space-y-8">
        <section :aria-label="$t('sheet.overview.counters')">
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <li v-for="counter in counters" :key="counter.label">
                    <StatTile
                        :label="counter.label"
                        :value="counter.value"
                        :value-color="readable(counter.color)"
                        :rule-color="counter.color?.base"
                    />
                </li>
            </ul>
        </section>

        <ScorePanel
            v-if="character.score"
            :score="character.score"
            :title="$t('sheet.overview.score_title')"
            :todo-url="todoUrl"
            :dimension-links="dimensionLinks"
            :share-data="shareData"
            share-variant="personal"
            @navigate="onNavigate"
        />
        <EmptyState
            v-else
            :icon="Gauge"
            :title="$t('sheet.overview.score_unavailable.title')"
            :message="$t('sheet.overview.score_unavailable.message')"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Gauge } from 'lucide-vue-next';
import { dimensionColor, readableVariants, rgbToHex } from '../utils/wowColors';
import { useWowColor } from '../composables/useWowColor';
import { useLocaleFormat } from '../composables/useLocaleFormat';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';
import { useTranslate } from '../composables/useTranslate';
import { DIMENSION_VIEWS, useSheetNavigation } from '../composables/useSheetNavigation';
import EmptyState from './ui/EmptyState.vue';
import StatTile from './ui/StatTile.vue';
import ScorePanel from './ScorePanel.vue';

const props = defineProps({
    character: { type: Object, required: true },
    realm: { type: String, required: true },
    name: { type: String, required: true },
    isOwner: { type: Boolean, default: false },
});

const TODO_PAGE = '/mon-compte/a-faire';

const { urlOf, show } = useSheetNavigation(() => props.realm, () => props.name);

const t = useTranslate();
const { formatNumber } = useLocaleFormat();
const { localizedUrl } = useLocalizedUrl();

const { readable, safe } = useWowColor();

// Each counter takes the colour of its dimension of the score, as in the radar.
const counters = computed(() => {
    const list = [
        { label: t('score.dimension.mounts'), value: formatNumber(props.character.mountsCount), color: dimensionColor('mounts') },
        { label: t('score.dimension.pets'), value: formatNumber(props.character.petsCount), color: dimensionColor('pets') },
        { label: t('score.dimension.decor'), value: formatNumber(props.character.decorCount), color: dimensionColor('decor') },
        { label: t('sheet.overview.reputations_done'), value: formatNumber(props.character.exaltedCount), color: dimensionColor('reputations') },
        { label: t('sheet.overview.achievement_points'), value: formatNumber(props.character.achievementPoints), color: dimensionColor('achievements') },
    ];

    const keystone = props.character.mythicKeystone;
    if (keystone?.rating) {
        list.push({
            label: t('sheet.overview.mythic_rating'),
            value: formatNumber(Math.round(keystone.rating)),
            color: safe((rgb) => readableVariants(rgbToHex(rgb)), keystone.rating_color),
        });
    }

    return list;
});

// The list of what is left belongs to the account: only its owner is sent there, on this character.
const todoUrl = computed(() => (props.isOwner ? `${localizedUrl(TODO_PAGE)}?${new URLSearchParams({ personnage: props.character.name })}` : ''));

const dimensionLinks = computed(() => Object.fromEntries(
    Object.entries(DIMENSION_VIEWS).map(([key, view]) => [key, urlOf(view.section, view.sub)]),
));

function onNavigate(key) {
    const view = DIMENSION_VIEWS[key];
    if (view) {
        show(view.section, view.sub);
    }
}

const shareData = computed(() => ({
    variant: 'personal',
    characterName: props.character.name,
    characterRealm: props.character.realm,
    characterClass: props.character.class,
    characterRace: props.character.race,
    characterLevel: props.character.level,
    classId: props.character.classId,
    globalScore: props.character.score?.global,
    rank: props.character.score?.rank,
    dimensions: props.character.score?.dimensions,
}));
</script>
