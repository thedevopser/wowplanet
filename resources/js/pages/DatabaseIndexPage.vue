<template>
    <div class="space-y-8 py-6 sm:py-8">
        <Head>
            <title>{{ meta.title }}</title>
            <meta name="description" :content="meta.description">
            <link rel="canonical" :href="meta.canonicalUrl">
            <meta property="og:type" :content="meta.ogType">
            <meta property="og:title" :content="meta.ogTitle">
            <meta property="og:description" :content="meta.ogDescription">
            <meta property="og:image" :content="meta.ogImage">
            <meta property="og:url" :content="meta.ogUrl">
            <meta property="og:site_name" content="WowPlanet">
            <meta property="og:locale" :content="$t('locale.og')">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" :content="meta.ogTitle">
            <meta name="twitter:description" :content="meta.ogDescription">
            <meta name="twitter:image" :content="meta.ogImage">
        </Head>

        <div class="mx-auto max-w-3xl text-center">
            <h1 class="mb-4 font-display text-3xl font-bold text-default sm:text-4xl md:text-5xl">{{ $t('database.index.title') }}</h1>
            <p class="text-base leading-relaxed text-muted sm:text-lg">
                {{ $t('database.index.intro') }}
            </p>
        </div>

        <ul class="mx-auto grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="section in SECTIONS"
                :key="section.key"
                as="li"
                variant="interactive"
                class="flex flex-col items-center gap-2 border-t-4 p-6 text-center"
                :style="{ borderTopColor: colorOf(section).base }"
            >
                <span class="mb-2 flex size-14 items-center justify-center rounded-ui-md border border-default bg-surface-raised p-3" :style="{ color: readable(colorOf(section)) }">
                    <CategoryIcon :category="section.icon" />
                </span>
                <h2 class="text-xl font-semibold text-default">
                    <Link :href="localizedUrl(section.path)" class="hover:underline focus-visible:outline-none after:absolute after:inset-0">{{ $t(`database.sections.${section.key}.label`) }}</Link>
                </h2>
                <p class="text-sm text-muted">{{ $t(`database.sections.${section.key}.description`) }}</p>
                <p class="text-2xl font-bold tabular-nums text-default">{{ formatCount(counts[section.countKey]) }}</p>
                <p v-if="section.countKey === 'recipes' && counts.professions" class="text-xs text-subtle">{{ $t('database.index.professions_count', { count: String(counts.professions) }) }}</p>
            </Card>
        </ul>

        <p class="mx-auto max-w-lg text-center text-sm text-muted">
            {{ $t('database.index.footer_data') }}
            <br>{{ $t('database.index.footer_login') }}
        </p>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';
import DatabaseLayout from '../layouts/DatabaseLayout.vue';

export default {
    layout: [AppLayout, DatabaseLayout],
};
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CategoryIcon from '../components/CategoryIcon.vue';
import Card from '../components/ui/Card.vue';
import { useWowColor } from '../composables/useWowColor';
import { dimensionColor } from '../utils/wowColors';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';

const SECTIONS = [
    { key: 'mounts', path: '/base-de-donnees/montures', icon: 'mounts', dimension: 'mounts', countKey: 'mounts' },
    { key: 'achievements', path: '/base-de-donnees/hauts-faits', icon: 'achievements', dimension: 'achievements', countKey: 'achievements' },
    { key: 'quests', path: '/base-de-donnees/quetes', icon: 'quests', dimension: 'quests', countKey: 'quests' },
    { key: 'pets', path: '/base-de-donnees/mascottes', icon: 'pets', dimension: 'pets', countKey: 'pets' },
    { key: 'decors', path: '/base-de-donnees/decorations', icon: 'decor', dimension: 'decor', countKey: 'decors' },
    { key: 'appearances', path: '/base-de-donnees/garde-robe', icon: 'transmog', dimension: 'transmog', countKey: 'appearances' },
    { key: 'professions', path: '/base-de-donnees/professions', icon: 'professions', dimension: 'professions', countKey: 'recipes' },
];

defineProps({
    meta: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
});

const { readable } = useWowColor();

const colorOf = (section) => dimensionColor(section.dimension);
const locale = useActiveLocale();
const { localizedUrl } = useLocalizedUrl();
const formatCount = (count) => (count ? count.toLocaleString(locale.value) : '—');
</script>
