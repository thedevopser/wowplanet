<template>
    <div class="space-y-6">
        <Card v-if="status === 'computing'" data-computing class="mx-auto max-w-xl space-y-3 p-5 sm:p-6">
            <div role="status" aria-live="polite" class="flex items-center gap-3">
                <Spinner />
                <p class="text-lg font-semibold text-default">Lecture de vos personnages…</p>
            </div>
            <p class="text-xs text-subtle">Deux appels à Blizzard par personnage au niveau maximum : quelques secondes.</p>
        </Card>

        <CardGridSkeleton v-else-if="status === 'loading'" label="Chargement de votre semaine" :count="3" />

        <ErrorState v-else-if="status === 'error'" title="Semaine indisponible" :message="errorMessage" @retry="start" />

        <EmptyState
            v-else-if="week.characters.length === 0"
            :icon="CalendarDays"
            title="Aucun personnage à suivre cette semaine"
            message="La Grande Chambre forte, les raids et les clés de la saison ne s’ouvrent qu’au niveau maximum."
        />

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1 text-sm text-muted">
                    <p>Ce qu’il reste à chaque personnage avant la remise à zéro du mercredi.</p>
                    <p v-if="week.belowMaxLevel > 0" data-below-max-level class="text-xs text-subtle">{{ belowMaxLevelText }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <p v-if="updatedAt" data-updated-at class="text-xs text-subtle">Mis à jour le {{ updatedAt }}</p>
                    <Button variant="secondary" :disabled="refreshing" @click="refresh">
                        <Icon :icon="RotateCw" size="sm" :class="{ 'motion-safe:animate-spin': refreshing }" />
                        Mettre à jour
                    </Button>
                </div>
            </div>

            <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <WeekCharacterCard v-for="character in week.characters" :key="`${character.realmSlug}/${character.name}`" :character="character" />
            </ul>

            <p data-week-limits class="text-xs text-subtle">
                Le décompte Mythique+ ne voit que la meilleure clé de chaque donjon : deux clés dans le même donjon comptent pour une,
                et les donjons héroïques et mythiques 0 n’apparaissent pas. Blizzard ne fournit rien sur les Gouffres, les quêtes hebdomadaires
                ni l’état réel de la chambre forte.
            </p>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CalendarDays, RotateCw } from 'lucide-vue-next';
import { useAccountComputation } from '../../composables/useAccountComputation';
import CardGridSkeleton from '../CardGridSkeleton.vue';
import Button from '../ui/Button.vue';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import Icon from '../ui/Icon.vue';
import Spinner from '../ui/Spinner.vue';
import WeekCharacterCard from './WeekCharacterCard.vue';

const EMPTY_WEEK = Object.freeze({ characters: [], belowMaxLevel: 0, maxLevel: 0, updatedAt: null });

const { status, data, errorMessage, refreshing, start, refresh } = useAccountComputation({
    endpoint: '/api/account/week',
    refreshEndpoint: '/api/account/week/refresh',
    messages: { failed: 'Le calcul de votre semaine a échoué. Réessayez dans un instant.', unavailable: 'La semaine n’a pas pu être chargée.' },
});

const week = computed(() => data.value ?? EMPTY_WEEK);

const belowMaxLevelText = computed(() => {
    const { belowMaxLevel, maxLevel } = week.value;

    return belowMaxLevel > 1
        ? `${belowMaxLevel} personnages sous le niveau ${maxLevel} ne sont pas affichés.`
        : `${belowMaxLevel} personnage sous le niveau ${maxLevel} n’est pas affiché.`;
});

const updatedAt = computed(() => {
    if (!week.value.updatedAt) return '';

    return new Date(week.value.updatedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
});
</script>
