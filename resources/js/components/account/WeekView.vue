<template>
    <div class="space-y-6">
        <Card v-if="status === 'computing'" data-computing class="mx-auto max-w-xl space-y-3 p-5 sm:p-6">
            <div role="status" aria-live="polite" class="flex items-center gap-3">
                <Spinner />
                <p class="text-lg font-semibold text-default">{{ $t('account.week.reading') }}</p>
            </div>
            <p class="text-xs text-subtle">{{ $t('account.week.reading_detail') }}</p>
        </Card>

        <CardGridSkeleton v-else-if="status === 'loading'" :label="$t('account.week.loading')" :count="3" />

        <ErrorState v-else-if="status === 'error'" :title="$t('account.week.unavailable')" :message="errorMessage" @retry="start" />

        <EmptyState
            v-else-if="week.characters.length === 0"
            :icon="CalendarDays"
            :title="$t('account.week.none.title')"
            :message="$t('account.week.none.message')"
        />

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1 text-sm text-muted">
                    <p>{{ $t('account.week.intro') }}</p>
                    <p v-if="week.belowMaxLevel > 0" data-below-max-level class="text-xs text-subtle">{{ belowMaxLevelText }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <p v-if="updatedAt" data-updated-at class="text-xs text-subtle">{{ $t('account.updated_at', { date: updatedAt }) }}</p>
                    <Button variant="secondary" :disabled="refreshing" @click="refresh">
                        <Icon :icon="RotateCw" size="sm" :class="{ 'motion-safe:animate-spin': refreshing }" />
                        {{ $t('account.update') }}
                    </Button>
                </div>
            </div>

            <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <WeekCharacterCard v-for="character in week.characters" :key="`${character.realmSlug}/${character.name}`" :character="character" />
            </ul>

            <p data-week-limits class="text-xs text-subtle">
                {{ $t('account.week.limits') }}
            </p>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CalendarDays, RotateCw } from 'lucide-vue-next';
import { useAccountComputation } from '../../composables/useAccountComputation';
import { useLocaleFormat } from '../../composables/useLocaleFormat';
import { useTranslate, useTranslateChoice } from '../../composables/useTranslate';
import CardGridSkeleton from '../CardGridSkeleton.vue';
import Button from '../ui/Button.vue';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import Icon from '../ui/Icon.vue';
import Spinner from '../ui/Spinner.vue';
import WeekCharacterCard from './WeekCharacterCard.vue';

const EMPTY_WEEK = Object.freeze({ characters: [], belowMaxLevel: 0, maxLevel: 0, updatedAt: null });

const t = useTranslate();
const tChoice = useTranslateChoice();
const { formatDate } = useLocaleFormat();

const { status, data, errorMessage, refreshing, start, refresh } = useAccountComputation({
    endpoint: '/api/account/week',
    refreshEndpoint: '/api/account/week/refresh',
    messages: { failed: t('account.week.failed'), unavailable: t('account.week.not_loaded') },
});

const week = computed(() => data.value ?? EMPTY_WEEK);

const belowMaxLevelText = computed(() => {
    const { belowMaxLevel, maxLevel } = week.value;

    return tChoice('account.week.below_max_level', belowMaxLevel, { count: belowMaxLevel, level: maxLevel });
});

const updatedAt = computed(() => {
    if (!week.value.updatedAt) return '';

    return formatDate(week.value.updatedAt, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
});
</script>
