<template>
    <section class="mt-6">
        <h3>
            <button
                type="button"
                :aria-expanded="String(expanded)"
                :aria-controls="panelId"
                class="flex min-h-11 w-full items-center justify-between rounded-ui-md border border-default bg-surface px-5 py-4 text-left
                    transition-colors duration-fast hover:bg-surface-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                @click="toggle"
            >
                <span class="flex items-baseline gap-3">
                    <span class="text-lg font-semibold text-default">Talents</span>
                    <span v-if="talentData" class="text-sm text-muted">{{ talentData.spec_name }}</span>
                </span>
                <Icon :icon="ChevronDown" class="text-subtle transition-transform duration-fast" :class="{ 'rotate-180': expanded }" />
            </button>
        </h3>

        <div v-if="expanded" :id="panelId" class="mt-4 space-y-4">
            <div v-if="loading" role="status" aria-busy="true" class="space-y-3">
                <span class="sr-only">Chargement des talents…</span>
                <Skeleton class="h-11 w-2/3" />
                <Skeleton class="h-64 w-full" />
            </div>

            <ErrorState v-else-if="error" :message="error" @retry="fetchTalents" />

            <Tabs v-else-if="talentData" v-model="activeTab" :tabs="tabs" label="Arbres de talents" variant="secondary">
                <template #class>
                    <Card class="overflow-hidden p-4 sm:p-6">
                        <TalentTreeGrid :nodes="talentData.class_nodes" />
                    </Card>
                </template>
                <template #spec>
                    <Card class="overflow-hidden p-4 sm:p-6">
                        <TalentTreeGrid :nodes="talentData.spec_nodes" />
                    </Card>
                </template>
                <template #hero>
                    <Card class="space-y-4 overflow-hidden p-4 sm:p-6">
                        <div v-if="talentData.hero_trees.length > 1" class="flex flex-wrap gap-2">
                            <Button
                                v-for="tree in talentData.hero_trees"
                                :key="tree.id"
                                size="sm"
                                :variant="activeHeroTreeId === tree.id ? 'secondary' : 'ghost'"
                                :aria-pressed="String(activeHeroTreeId === tree.id)"
                                @click="activeHeroTreeId = tree.id"
                            >
                                {{ tree.name }}
                                <span v-if="tree.active" class="inline-flex items-center gap-1 text-xs text-accent">
                                    <Icon :icon="Star" size="sm" class="fill-current" />
                                    actif
                                </span>
                            </Button>
                        </div>
                        <TalentTreeGrid v-if="activeHeroTree" :nodes="activeHeroTree.nodes" />
                    </Card>
                </template>
            </Tabs>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import axios from 'axios';
import { ChevronDown, Star } from 'lucide-vue-next';
import { useWowheadTooltips } from '../composables/useWowheadTooltips';
import Button from './ui/Button.vue';
import Card from './ui/Card.vue';
import ErrorState from './ui/ErrorState.vue';
import Icon from './ui/Icon.vue';
import Skeleton from './ui/Skeleton.vue';
import Tabs from './ui/Tabs.vue';
import TalentTreeGrid from './TalentTreeGrid.vue';

useWowheadTooltips();

const props = defineProps({
    realm: { type: String, required: true },
    name: { type: String, required: true },
});

const panelId = useId();
const expanded = ref(false);
const loading = ref(false);
const error = ref(null);
const talentData = ref(null);
const activeTab = ref('class');
const activeHeroTreeId = ref(null);
let fetched = false;

const tabs = computed(() => {
    const list = [
        { value: 'class', label: talentData.value ? talentData.value.class_name : 'Classe' },
        { value: 'spec', label: talentData.value ? talentData.value.spec_name : 'Spécialisation' },
    ];

    if (talentData.value?.hero_trees?.length) {
        list.push({ value: 'hero', label: 'Talents héroïques' });
    }

    return list;
});

const activeHeroTree = computed(() => {
    if (!talentData.value?.hero_trees?.length) return null;
    return talentData.value.hero_trees.find(t => t.id === activeHeroTreeId.value) || talentData.value.hero_trees[0];
});

async function fetchTalents() {
    if (fetched) return;
    fetched = true;
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(`/api/character/${encodeURIComponent(props.realm)}/${encodeURIComponent(props.name)}/talents`);
        talentData.value = response.data;

        // Default to the active hero tree
        const activeTree = talentData.value.hero_trees?.find(t => t.active);
        if (activeTree) {
            activeHeroTreeId.value = activeTree.id;
        } else if (talentData.value.hero_trees?.length) {
            activeHeroTreeId.value = talentData.value.hero_trees[0].id;
        }
    } catch {
        error.value = 'Impossible de charger les talents.';
        fetched = false;
    } finally {
        loading.value = false;
    }
}

function toggle() {
    expanded.value = !expanded.value;
    if (expanded.value && !fetched) {
        fetchTalents();
    }
}
</script>
