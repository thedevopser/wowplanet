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
import { useCatalogLocale } from '../composables/useCatalogLocale';
import ExpansionChecklistTab from './sheet/ExpansionChecklistTab.vue';

const LABELS = Object.freeze({
    title: 'score.dimension.quests',
    description: 'sheet.quests.description',
    groups: 'sheet.quests.groups',
    search: 'sheet.quests.search',
    hide: 'sheet.quests.hide',
    noMatch: 'sheet.quests.no_match',
    noData: 'sheet.quests.no_data',
});

const store = useCharacterStore();
const { wowheadUrl } = useCatalogLocale();
</script>
