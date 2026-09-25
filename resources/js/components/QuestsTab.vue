<template>
    <ExpansionChecklistTab
        collection-type="quests"
        groups-key="zones"
        :labels="LABELS"
        :item-url="(item) => wowheadUrl('quest', item)"
        :is-elsewhere="store.isQuestCompletedElsewhere"
        :owner-of="store.getQuestOwner"
    />
</template>

<script setup>
import { useCharacterStore } from '../stores/character';
import { wowheadUrl } from '../utils/scoreRecommendations';
import ExpansionChecklistTab from './sheet/ExpansionChecklistTab.vue';

const LABELS = Object.freeze({
    title: 'Quêtes',
    description: 'Progression des quêtes de l’extension',
    groups: 'Décomposition par zone',
    search: 'Rechercher une quête…',
    hide: 'Masquer les quêtes faites',
    noMatch: 'Aucune quête ne correspond à ces filtres.',
    noData: 'Aucune quête connue pour cette extension.',
});

const store = useCharacterStore();
</script>
