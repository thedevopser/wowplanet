<template>
    <div class="space-y-8">
        <section aria-label="Compteurs clés">
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
            title="Score de complétion"
            :recommendations="recommendations"
            :dimension-links="dimensionLinks"
            :share-data="shareData"
            share-variant="personal"
            @navigate="onNavigate"
        />
        <EmptyState
            v-else
            :icon="Gauge"
            title="Score indisponible"
            message="Le score de ce personnage n’a pas pu être calculé. Il apparaîtra au prochain chargement de la fiche."
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Gauge } from 'lucide-vue-next';
import { buildRecommendations } from '../utils/scoreRecommendations';
import { dimensionColor, readableVariants, rgbToHex } from '../utils/wowColors';
import { useWowColor } from '../composables/useWowColor';
import { DIMENSION_VIEWS, useSheetNavigation } from '../composables/useSheetNavigation';
import EmptyState from './ui/EmptyState.vue';
import StatTile from './ui/StatTile.vue';
import ScorePanel from './ScorePanel.vue';

const props = defineProps({
    character: { type: Object, required: true },
    realm: { type: String, required: true },
    name: { type: String, required: true },
});

const { urlOf, show } = useSheetNavigation(() => props.realm, () => props.name);

const formatNumber = (value) => Number(value ?? 0).toLocaleString('fr-FR');

const { readable, safe } = useWowColor();

// Each counter takes the colour of its dimension of the score, as in the radar.
const counters = computed(() => {
    const list = [
        { label: 'Montures', value: formatNumber(props.character.mountsCount), color: dimensionColor('mounts') },
        { label: 'Mascottes', value: formatNumber(props.character.petsCount), color: dimensionColor('pets') },
        { label: 'Décorations', value: formatNumber(props.character.decorCount), color: dimensionColor('decor') },
        { label: 'Réputations terminées', value: formatNumber(props.character.exaltedCount), color: dimensionColor('reputations') },
        { label: 'Points de hauts-faits', value: formatNumber(props.character.achievementPoints), color: dimensionColor('achievements') },
    ];

    const keystone = props.character.mythicKeystone;
    if (keystone?.rating) {
        list.push({
            label: 'Cote Mythique+',
            value: formatNumber(Math.round(keystone.rating)),
            color: safe((rgb) => readableVariants(rgbToHex(rgb)), keystone.rating_color),
        });
    }

    return list;
});

const recommendations = computed(() => buildRecommendations(props.character));

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
