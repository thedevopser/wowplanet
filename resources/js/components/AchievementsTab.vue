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
import { useCatalogLocale } from '../composables/useCatalogLocale';
import ExpansionChecklistTab from './sheet/ExpansionChecklistTab.vue';

const LABELS = Object.freeze({
    title: 'score.dimension.achievements',
    description: 'sheet.achievements.description',
    groups: 'sheet.achievements.groups',
    search: 'sheet.achievements.search',
    hide: 'sheet.achievements.hide',
    noMatch: 'sheet.achievements.no_match',
    noData: 'sheet.achievements.no_data',
});

const store = useCharacterStore();
const { wowheadUrl } = useCatalogLocale();
</script>
