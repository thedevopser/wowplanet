<template>
    <div class="space-y-6">
        <div v-if="loading" data-testid="pvp-loading" role="status" aria-busy="true" class="space-y-4">
            <span class="sr-only">Chargement du PvP…</span>
            <Skeleton class="h-28 w-full" />
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Skeleton v-for="index in 3" :key="index" class="h-32 w-full" />
            </div>
        </div>

        <ErrorState v-else-if="error" message="Impossible de charger les données PvP de ce personnage." @retry="load" />

        <EmptyState v-else-if="!pvp" :icon="Swords" title="Joueur contre joueur" message="Aucune donnée PvP pour ce personnage." />

        <template v-else>
            <Card data-testid="pvp-header" class="flex flex-wrap items-end justify-between gap-4 p-5 sm:p-6">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-default">Joueur contre joueur</h2>
                    <p v-if="pvp.season_id" class="mt-1 text-sm text-muted">Saison {{ pvp.season_id }}</p>
                </div>
                <dl class="flex items-end gap-6 text-right">
                    <div v-if="pvp.best_rating">
                        <dt class="text-xs text-subtle">Meilleure cote</dt>
                        <dd class="text-3xl font-bold tabular-nums text-accent">{{ pvp.best_rating }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-subtle">Niveau d’honneur</dt>
                        <dd class="text-2xl font-bold tabular-nums text-default">{{ pvp.honor_level }}</dd>
                    </div>
                </dl>
            </Card>

            <Card
                v-if="pvp.battlegrounds && pvp.battlegrounds.played > 0"
                data-testid="pvp-battlegrounds"
                class="flex flex-wrap items-center justify-between gap-4 p-4"
            >
                <span class="text-sm font-semibold text-default">Champs de bataille non cotés</span>
                <RecordLine :played="pvp.battlegrounds.played" :won="pvp.battlegrounds.won" :lost="pvp.battlegrounds.lost" :win-rate="pvp.battlegrounds.win_rate" />
            </Card>

            <section v-for="group in pvp.groups" :key="group.key" :data-testid="`pvp-group-${group.key}`" class="space-y-4">
                <h3 class="text-lg font-semibold text-default">{{ group.label }}</h3>
                <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card v-for="bracket in group.brackets" :key="bracket.slug" as="li" :data-testid="`pvp-bracket-${bracket.slug}`" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-default">{{ bracket.label }}</p>
                                <p v-if="bracket.tier_name" class="mt-1 flex items-center gap-1.5 text-xs text-muted">
                                    <img v-if="bracket.tier_icon_url" :src="bracket.tier_icon_url" alt="" class="size-4 rounded-ui-sm" loading="lazy">
                                    <span class="truncate">{{ bracket.tier_name }}</span>
                                </p>
                            </div>
                            <p class="shrink-0 text-2xl font-bold tabular-nums text-accent">{{ bracket.rating }}</p>
                        </div>
                        <RecordLine :won="bracket.won" :lost="bracket.lost" :win-rate="bracket.win_rate" class="border-t border-default pt-3" />
                        <p v-if="bracket.weekly && bracket.weekly.played > 0" class="text-xs text-subtle">
                            Cette semaine : {{ bracket.weekly.played }} joués — {{ bracket.weekly.won }} victoires, {{ bracket.weekly.lost }} défaites
                        </p>
                    </Card>
                </ul>
            </section>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { Swords } from 'lucide-vue-next';
import Card from './ui/Card.vue';
import EmptyState from './ui/EmptyState.vue';
import ErrorState from './ui/ErrorState.vue';
import Skeleton from './ui/Skeleton.vue';
import RecordLine from './sheet/RecordLine.vue';

const props = defineProps({
    realm: { type: String, required: true },
    name: { type: String, required: true },
});

const loading = ref(true);
const error = ref(false);
const pvp = ref(null);

// Lazy: the tab mounts only when opened, so PvP costs nothing to the many profiles without it.
async function load() {
    loading.value = true;
    error.value = false;
    try {
        const response = await axios.get(`/api/character/${encodeURIComponent(props.realm)}/${encodeURIComponent(props.name)}/pvp`);
        pvp.value = response.data?.pvp ?? null;
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
