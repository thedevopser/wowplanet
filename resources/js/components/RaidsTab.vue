<template>
    <div class="space-y-6">
        <SectionHeader title="Raids" description="Progression de la saison en cours, par difficulté" />

        <EmptyState v-if="!raids || !raids.length" :icon="Swords" title="Aucun raid" message="Aucune progression de raid pour la saison en cours." />

        <template v-else>
            <Card v-for="raid in raids" :key="raid.instance_id" as="section" :data-raid="raid.instance_id" class="overflow-hidden">
                <div class="space-y-4 p-5 sm:p-6">
                    <h3 class="text-lg font-semibold text-default">
                        <button
                            type="button"
                            :aria-expanded="String(!isCollapsed(raid))"
                            :aria-controls="`raid-body-${raid.instance_id}`"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-ui-sm text-left
                                focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            @click="toggle(raid)"
                        >
                            <span class="text-xl">{{ raid.instance_name }}</span>
                            <Icon :icon="ChevronDown" class="shrink-0 text-subtle transition-transform duration-fast" :class="{ '-rotate-90': isCollapsed(raid) }" />
                        </button>
                    </h3>

                    <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <li
                            v-for="summary in summariesOf(raid)"
                            :key="summary.type"
                            :data-difficulty="summary.type"
                            class="space-y-2 rounded-ui-md border p-3"
                            :class="summary.started ? 'border-default bg-surface-raised' : 'border-dashed border-default'"
                        >
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-sm font-semibold" :style="summary.started ? { color: readable(colorOf(summary.quality)) } : undefined" :class="{ 'text-subtle': !summary.started }">
                                    {{ summary.label }}
                                </span>
                                <span v-if="summary.started" class="text-sm font-bold tabular-nums text-default">{{ summary.completed }}/{{ summary.total }}</span>
                            </div>
                            <PipGauge
                                v-if="summary.started && summary.total > 0"
                                :filled="summary.completed"
                                :total="summary.total"
                                :color="colorOf(summary.quality).base"
                                :label="`${summary.label} : ${summary.completed} boss vaincus sur ${summary.total}`"
                            />
                            <p class="flex items-center gap-1 text-xs" :class="summary.cleared ? 'text-success' : 'text-subtle'">
                                <Icon v-if="summary.cleared" :icon="CircleCheck" size="sm" />
                                {{ summary.cleared ? 'Terminé' : summary.started ? 'En cours' : 'Non entamé' }}
                            </p>
                        </li>
                    </ul>
                </div>

                <div v-if="!isCollapsed(raid)" :id="`raid-body-${raid.instance_id}`" class="relative overflow-x-auto border-t border-default" tabindex="0" role="region" :aria-label="`Boss vaincus de ${raid.instance_name}, par difficulté`">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Boss vaincus de {{ raid.instance_name }}, par difficulté</caption>
                        <thead>
                            <tr class="text-xs text-subtle">
                                <th scope="col" class="px-5 py-3 text-left font-medium sm:px-6">Boss</th>
                                <th
                                    v-for="type in matrixOf(raid).difficulties"
                                    :key="type"
                                    scope="col"
                                    class="w-16 px-2 py-3 text-center font-semibold sm:w-24"
                                    :style="{ color: readable(colorOf(qualityOf(type))) }"
                                >
                                    <abbr :title="labelOf(type)" class="no-underline">{{ shortOf(type) }}</abbr>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in matrixOf(raid).rows" :key="row.name" class="border-t border-default">
                                <th scope="row" class="px-5 py-2.5 text-left font-medium text-default sm:px-6">{{ row.name }}</th>
                                <td v-for="type in matrixOf(raid).difficulties" :key="type" class="px-2 py-2.5 text-center">
                                    <span v-if="row.kills[type]" class="inline-flex flex-col items-center gap-0.5">
                                        <Icon :icon="CircleCheck" size="sm" :style="{ color: readable(colorOf(qualityOf(type))) }" />
                                        <span class="sr-only">Vaincu le {{ formatDate(row.kills[type]) }}</span>
                                        <span aria-hidden="true" class="text-xs tabular-nums text-subtle">{{ formatShortDate(row.kills[type]) }}</span>
                                    </span>
                                    <span v-else class="text-subtle">
                                        <span aria-hidden="true">–</span>
                                        <span class="sr-only">Non vaincu</span>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="matrixOf(raid).unknownCount > 0">
                            <tr class="border-t border-default">
                                <td :colspan="matrixOf(raid).difficulties.length + 1" class="px-5 py-2.5 text-xs text-subtle sm:px-6">
                                    {{ matrixOf(raid).unknownCount }} boss jamais vaincus, dont Blizzard ne donne pas le nom
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ChevronDown, CircleCheck, Swords } from 'lucide-vue-next';
import { useCharacterStore } from '../stores/character';
import { useWowColor } from '../composables/useWowColor';
import { DIFFICULTIES, bossMatrix, difficultySummaries, sortNewestFirst } from '../utils/raids';
import { qualityColor } from '../utils/wowColors';
import PipGauge from './sheet/PipGauge.vue';
import Card from './ui/Card.vue';
import EmptyState from './ui/EmptyState.vue';
import Icon from './ui/Icon.vue';
import SectionHeader from './ui/SectionHeader.vue';

const store = useCharacterStore();

const raids = computed(() => (store.character?.raids ? sortNewestFirst(store.character.raids) : null));

const views = computed(() => new Map((raids.value ?? []).map((raid) => [raid.instance_id, { matrix: bossMatrix(raid), summaries: difficultySummaries(raid) }])));
const matrixOf = (raid) => views.value.get(raid.instance_id).matrix;
const summariesOf = (raid) => views.value.get(raid.instance_id).summaries;

const STORAGE_KEY = 'wowplanet-raids-collapsed';

// instance_id des raids repliés. Volontairement vide au départ : le localStorage
// n'est lu qu'au montage, pour que le rendu SSR et le premier rendu client
// coïncident (sinon Vue signale un hydration mismatch).
const collapsed = ref(new Set());

onMounted(() => {
    if (typeof localStorage === 'undefined') return;

    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
        if (Array.isArray(stored)) {
            collapsed.value = new Set(stored);
        }
    } catch {
        // Entrée corrompue : on repart tout déplié plutôt que de casser l'onglet.
    }
});

// The folds are a convenience: a browser that refuses to store them keeps them for the visit.
function persist() {
    if (typeof localStorage === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsed.value]));
    } catch {
        // Nothing to do: the fold still applies until the page is left.
    }
}

function isCollapsed(raid) {
    return collapsed.value.has(raid.instance_id);
}

function toggle(raid) {
    const next = new Set(collapsed.value);
    if (!next.delete(raid.instance_id)) {
        next.add(raid.instance_id);
    }
    collapsed.value = next;
    persist();
}

const { readable } = useWowColor();

const byType = Object.fromEntries(DIFFICULTIES.map((difficulty) => [difficulty.type, difficulty]));
const qualityOf = (type) => byType[type].quality;
const labelOf = (type) => byType[type].label;
const shortOf = (type) => byType[type].short;
const colorOf = (quality) => qualityColor(quality);

function formatDate(timestamp) {
    return new Date(timestamp).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

function formatShortDate(timestamp) {
    return new Date(timestamp).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
}
</script>
