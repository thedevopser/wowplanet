<template>
    <div class="space-y-6">
        <EmptyState v-if="!professions.length" :icon="Hammer" :title="$t('sheet.professions.none.title')" :message="$t('sheet.professions.none.message')" />

        <template v-else>
            <div class="flex flex-wrap items-center gap-4">
                <Select v-model="professionParam" :label="$t('sheet.professions.profession')" :options="professionOptions" />
                <ExpansionFilter
                    v-if="!isArchaeology"
                    v-model="expansionId"
                    :expansions="store.expansions"
                    :collections="professionCollections"
                    collection-type="recipes"
                />
            </div>

            <template v-if="isArchaeology">
                <ProgressSummary
                    v-if="selected.global_max_skill_points > 0"
                    :title="selected.profession_name"
                    :description="$t('sheet.professions.global_skill')"
                    :completed="selected.global_skill_points ?? 0"
                    :total="selected.global_max_skill_points"
                    dimension="professions"
                />
                <EmptyState v-else :icon="Inbox" :title="selected.profession_name" :message="$t('sheet.professions.no_archaeology')" />
            </template>

            <EmptyState
                v-else-if="tierNotLearned"
                :icon="BookX"
                :title="selected.profession_name"
                :message="$t('sheet.professions.not_learned', { profession: selected.profession_name })"
            />

            <template v-else-if="expansion">
                <ProgressSummary
                    :title="selected.profession_name"
                    :description="isGathering ? $t('sheet.professions.skill_level') : $t('sheet.professions.recipes_learned')"
                    :completed="isGathering ? expansion.skill_points : expansion.completed"
                    :total="isGathering ? expansion.max_skill_points : expansion.total"
                    dimension="professions"
                />

                <Card v-if="!isGathering && expansion.max_skill_points > 0" data-skill class="space-y-2 p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-muted">{{ $t('sheet.professions.skill') }}</span>
                        <span class="tabular-nums text-default">{{ expansion.skill_points }} / {{ expansion.max_skill_points }}</span>
                    </div>
                    <ProgressBar
                        :value="expansion.skill_points"
                        :max="expansion.max_skill_points"
                        :aria-label="$t('common.progress_of', { label: $t('sheet.professions.skill'), completed: expansion.skill_points, total: expansion.max_skill_points })"
                    />
                    <BetterElsewhere v-if="betterSkill" :character="betterSkill.character_name" :detail="`${betterSkill.skill_points} / ${betterSkill.max_skill_points}`" />
                </Card>

                <template v-if="(expansion.categories ?? []).length">
                    <SearchFilter
                        v-model:search="search"
                        v-model:hide-completed="hideCompleted"
                        :placeholder="$t('sheet.professions.search')"
                        :hide-label="$t('sheet.professions.hide')"
                    />

                    <GroupedChecklist :groups="groups" :title="$t('sheet.professions.categories')" dimension="professions" :empty-message="$t('sheet.professions.no_match')">
                        <template #item="{ item }">
                            <a
                                :href="recipeUrl(item)"
                                target="_blank"
                                rel="noopener"
                                class="min-w-0 flex-1 truncate py-1 hover:underline"
                                :class="item.is_completed ? 'text-default' : 'text-muted'"
                            >{{ item.name }}</a>
                            <CompletionMark :done="item.is_completed" :elsewhere="store.isRecipeKnownElsewhere(item.id)" :owner="store.getRecipeOwner(item.id) ?? ''" />
                        </template>
                    </GroupedChecklist>
                </template>
            </template>

            <EmptyState v-else :icon="Inbox" :title="selected.profession_name" :message="$t('sheet.professions.no_data')" />
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { BookX, Hammer, Inbox } from 'lucide-vue-next';
import { useCharacterStore } from '../stores/character';
import { useQueryParam } from '../composables/useQueryParam';
import { useWowheadTooltips } from '../composables/useWowheadTooltips';
import { useCatalogLocale } from '../composables/useCatalogLocale';
import { useLocaleFormat } from '../composables/useLocaleFormat';
import { wowheadSearch } from '../utils/wowhead';
import SearchFilter from './SearchFilter.vue';
import Card from './ui/Card.vue';
import EmptyState from './ui/EmptyState.vue';
import ProgressBar from './ui/ProgressBar.vue';
import Select from './ui/Select.vue';
import BetterElsewhere from './sheet/BetterElsewhere.vue';
import CompletionMark from './sheet/CompletionMark.vue';
import ExpansionFilter from './sheet/ExpansionFilter.vue';
import GroupedChecklist from './sheet/GroupedChecklist.vue';
import ProgressSummary from './sheet/ProgressSummary.vue';

useWowheadTooltips();
const store = useCharacterStore();

const professions = computed(() => store.character?.professions ?? []);

const professionParam = useQueryParam('metier', String(professions.value[0]?.profession_id ?? ''));
const extension = useQueryParam('extension', String(store.latestExpansionId));
const expansionId = computed({
    get: () => Number(extension.value),
    set: (value) => {
        extension.value = String(value);
    },
});

const professionOptions = computed(() => professions.value.map((profession) => ({
    value: String(profession.profession_id),
    label: profession.profession_name,
    ...(profession.type === 'secondary' ? { hint: 'Secondaire' } : {}),
})));

const selected = computed(() => professions.value.find((profession) => String(profession.profession_id) === professionParam.value)
    ?? professions.value[0]
    ?? null);

const isArchaeology = computed(() => selected.value?.is_archaeology === true);

// Herbalism, mining and skinning teach no recipe: their progress is their skill.
const isGathering = computed(() => Object.values(selected.value?.expansions ?? {}).every((data) => data.total === 0));

const professionCollections = computed(() => Object.fromEntries(
    Object.entries(selected.value?.expansions ?? {}).map(([id, data]) => [id, {
        recipes: isGathering.value
            ? { completed: data.skill_points || 0, total: data.max_skill_points || 0 }
            : { completed: data.completed, total: data.total },
    }]),
));

const expansion = computed(() => selected.value?.expansions?.[expansionId.value] ?? null);

const tierNotLearned = computed(() => Boolean(expansion.value)
    && !expansion.value.has_tier
    && (expansion.value.tier_exists || isGathering.value));

const betterSkill = computed(() => {
    const best = store.getBestSkillPoints(selected.value?.profession_id, expansionId.value);
    if (!best || best.character_name === store.character?.name || best.skill_points <= (expansion.value?.skill_points || 0)) {
        return null;
    }

    return best;
});

const search = ref('');
const hideCompleted = ref(false);

watch([() => selected.value?.profession_id, expansionId], () => {
    search.value = '';
    hideCompleted.value = false;
});

const { compareNames } = useLocaleFormat();
const { wowheadLink } = useCatalogLocale();
const byName = (a, b) => compareNames(a.name, b.name);

const groups = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (expansion.value?.categories ?? [])
        .map((category) => {
            const items = (category.items ?? [])
                .filter((item) => !query || item.name.toLowerCase().includes(query))
                .filter((item) => !hideCompleted.value || !item.is_completed)
                .toSorted(byName);

            return { name: category.name, items, total: items.length, completed: items.filter((item) => item.is_completed).length };
        })
        .filter((category) => category.items.length > 0)
        .toSorted(byName);
});

const recipeUrl = (item) => wowheadLink(item.wowhead_spell_id ? `spell=${item.wowhead_spell_id}` : wowheadSearch(item.name));
</script>
