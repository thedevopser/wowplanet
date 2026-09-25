<template>
    <div class="space-y-6">
        <ExpansionFilter
            v-model="expansionId"
            :expansions="store.expansions"
            :collections="store.character.collections ?? {}"
            collection-type="reputations"
        />

        <template v-if="collection">
            <ProgressSummary
                title="Réputations"
                description="Factions dont la réputation est au maximum"
                :completed="progress.completed"
                :total="progress.total"
                dimension="reputations"
            />

            <SearchFilter
                v-model:search="search"
                v-model:hide-completed="hideCompleted"
                placeholder="Rechercher une faction…"
                hide-label="Masquer les terminées"
            >
                <template #extra-toggles>
                    <Button
                        size="sm"
                        :variant="hideUnstarted ? 'secondary' : 'ghost'"
                        :aria-pressed="String(hideUnstarted)"
                        @click="hideUnstarted = !hideUnstarted"
                    >
                        <Icon :icon="hideUnstarted ? EyeOff : Eye" size="sm" />
                        Masquer les non commencées
                    </Button>
                </template>
            </SearchFilter>

            <section v-if="factions.length" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-default">Factions</h3>
                    <Pagination v-model:page="page" :page-count="pageCount" label="Pages des factions" />
                </div>
                <ul class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="entry in visibleFactions" :key="entry.faction.id" as="li" data-faction class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <a
                                :href="`https://www.wowhead.com/fr/faction=${entry.faction.id}`"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-semibold hover:underline"
                                :class="entry.standing.started === false ? 'text-muted' : 'text-default'"
                            >{{ entry.faction.name }}</a>
                            <Badge v-if="entry.colorKey" tone="standing" :value="entry.colorKey" class="shrink-0">{{ standingLabel(entry.standing) }}</Badge>
                            <Badge v-else tone="neutral" class="shrink-0">{{ standingLabel(entry.standing) }}</Badge>
                        </div>
                        <template v-if="entry.faction.max > 0 && entry.faction.value > 0">
                            <ProgressBar
                                :value="entry.faction.value"
                                :max="entry.faction.max"
                                :color="colorOf(entry.colorKey)"
                                :aria-label="`${entry.faction.name} : ${formatNumber(entry.faction.value)} sur ${formatNumber(entry.faction.max)}`"
                            />
                            <p class="text-right text-xs tabular-nums text-subtle">
                                {{ formatNumber(entry.faction.value) }} / {{ formatNumber(entry.faction.max) }}
                            </p>
                        </template>
                        <BetterElsewhere v-if="entry.better" :character="entry.better.character_name" :detail="entry.better.standing_name" />
                    </Card>
                </ul>
            </section>
            <EmptyState v-else-if="progress.total === 0" :icon="Inbox" title="Rien à afficher" message="Aucune réputation pour cette extension." />
            <EmptyState v-else :icon="SearchX" title="Aucun résultat" message="Aucune faction ne correspond à ces filtres." />
        </template>
        <EmptyState v-else :icon="Inbox" title="Rien à afficher" message="Aucune réputation pour cette extension." />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Eye, EyeOff, Inbox, SearchX } from 'lucide-vue-next';
import { useCharacterStore } from '../stores/character';
import { useQueryParam } from '../composables/useQueryParam';
import { useWowColor } from '../composables/useWowColor';
import { useWowheadTooltips } from '../composables/useWowheadTooltips';
import { betterElsewhere, effectiveStanding, sortFactions, standingKey, standingLabel } from '../utils/reputations';
import { standingColor } from '../utils/wowColors';
import SearchFilter from './SearchFilter.vue';
import Badge from './ui/Badge.vue';
import Button from './ui/Button.vue';
import Card from './ui/Card.vue';
import EmptyState from './ui/EmptyState.vue';
import Icon from './ui/Icon.vue';
import Pagination from './ui/Pagination.vue';
import ProgressBar from './ui/ProgressBar.vue';
import BetterElsewhere from './sheet/BetterElsewhere.vue';
import ExpansionFilter from './sheet/ExpansionFilter.vue';
import ProgressSummary from './sheet/ProgressSummary.vue';

const PER_PAGE = 12;

useWowheadTooltips();
const store = useCharacterStore();
const { safe } = useWowColor();

const extension = useQueryParam('extension', String(store.latestExpansionId));
const expansionId = computed({
    get: () => Number(extension.value),
    set: (value) => {
        extension.value = String(value);
    },
});

const search = ref('');
const hideCompleted = ref(false);
const hideUnstarted = ref(false);
const page = ref(1);

const collection = computed(() => store.character?.collections?.[expansionId.value]?.reputations ?? null);

const standingOf = (faction) => effectiveStanding(faction, store.getBestFactionStanding(faction.id));

const progress = computed(() => {
    const factions = collection.value?.factions ?? [];

    return { total: factions.length, completed: factions.filter((faction) => standingOf(faction).completed).length };
});

const factions = computed(() => {
    const query = search.value.trim().toLowerCase();

    return sortFactions(collection.value?.factions ?? [], standingOf)
        .map((faction) => {
            const standing = standingOf(faction);

            return {
                faction,
                standing,
                colorKey: knownStandingKey(standing),
                better: betterElsewhere(faction, store.getBestFactionStanding(faction.id), store.character?.name),
            };
        })
        .filter((entry) => !query || entry.faction.name.toLowerCase().includes(query))
        .filter((entry) => !hideCompleted.value || !entry.standing.completed)
        .filter((entry) => !hideUnstarted.value || entry.standing.started !== false);
});

const pageCount = computed(() => Math.ceil(factions.value.length / PER_PAGE));
const visibleFactions = computed(() => factions.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));

// Blizzard adds tiers over time (friendships go beyond exalted): an unknown one shows
// uncoloured instead of breaking the whole tab.
function knownStandingKey(standing) {
    const key = standingKey(standing);

    return key !== null && safe(standingColor, key) !== null ? key : null;
}

const colorOf = (key) => (key === null ? undefined : safe(standingColor, key)?.base);
const formatNumber = (value) => Number(value).toLocaleString('fr-FR');

watch([expansionId, search, hideCompleted, hideUnstarted], () => {
    page.value = 1;
});
</script>
