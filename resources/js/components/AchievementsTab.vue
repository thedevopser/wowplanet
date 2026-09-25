<template>
    <ExpansionChecklistTab
        collection-type="achievements"
        groups-key="categories"
        :labels="LABELS"
        :item-url="(item) => wowheadUrl('achievement', item)"
        :is-elsewhere="store.isAchievementCompletedElsewhere"
        :owner-of="store.getAchievementOwner"
        with-icon
    />
</template>

<script setup>
import { useCharacterStore } from '../stores/character';
import { wowheadUrl } from '../utils/scoreRecommendations';
import ExpansionChecklistTab from './sheet/ExpansionChecklistTab.vue';

const LABELS = Object.freeze({
    title: 'Hauts-faits',
    description: 'Hauts-faits obtenus sur l’extension',
    groups: 'Catégories de hauts-faits',
    search: 'Rechercher un haut-fait…',
    hide: 'Masquer les hauts-faits obtenus',
    noMatch: 'Aucun haut-fait ne correspond à ces filtres.',
    noData: 'Aucun haut-fait connu pour cette extension.',
});

const store = useCharacterStore();
</script>
