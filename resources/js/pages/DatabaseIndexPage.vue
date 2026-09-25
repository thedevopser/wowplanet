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
            <meta property="og:locale" content="fr_FR">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" :content="meta.ogTitle">
            <meta name="twitter:description" :content="meta.ogDescription">
            <meta name="twitter:image" :content="meta.ogImage">
        </Head>

        <div class="mx-auto max-w-3xl text-center">
            <h1 class="mb-4 font-display text-3xl font-bold text-default sm:text-4xl md:text-5xl">Base de données WoW</h1>
            <p class="text-base leading-relaxed text-muted sm:text-lg">
                Explorez la base de données complète de World of Warcraft entièrement en français.
                Montures, hauts-faits, quêtes, mascottes, décorations, garde-robe et professions.
            </p>
        </div>

        <ul class="mx-auto grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="section in SECTIONS"
                :key="section.path"
                as="li"
                variant="interactive"
                class="flex flex-col items-center gap-2 border-t-4 p-6 text-center"
                :style="{ borderTopColor: colorOf(section).base }"
            >
                <span class="mb-2 flex size-14 items-center justify-center rounded-ui-md border border-default bg-surface-raised p-3" :style="{ color: readable(colorOf(section)) }">
                    <CategoryIcon :category="section.icon" />
                </span>
                <h2 class="text-xl font-semibold text-default">
                    <Link :href="section.path" class="hover:underline focus-visible:outline-none after:absolute after:inset-0">{{ section.label }}</Link>
                </h2>
                <p class="text-sm text-muted">{{ section.description }}</p>
                <p class="text-2xl font-bold tabular-nums text-default">{{ formatCount(counts[section.countKey]) }}</p>
                <p v-if="section.countKey === 'recipes' && counts.professions" class="text-xs text-subtle">{{ counts.professions }} professions</p>
            </Card>
        </ul>

        <p class="mx-auto max-w-lg text-center text-sm text-muted">
            Données de référence du jeu entièrement en français.
            <br>Connectez-vous avec Battle.net pour suivre votre progression personnelle.
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

const SECTIONS = [
    { path: '/base-de-donnees/montures', label: 'Montures', description: 'Triées par catégorie et source d’obtention', icon: 'mounts', dimension: 'mounts', countKey: 'mounts' },
    { path: '/base-de-donnees/hauts-faits', label: 'Hauts-faits', description: 'Classés par extension et catégorie', icon: 'achievements', dimension: 'achievements', countKey: 'achievements' },
    { path: '/base-de-donnees/quetes', label: 'Quêtes', description: 'Triées par extension et zone', icon: 'quests', dimension: 'quests', countKey: 'quests' },
    { path: '/base-de-donnees/mascottes', label: 'Mascottes', description: 'Triées par catégorie et source', icon: 'pets', dimension: 'pets', countKey: 'pets' },
    { path: '/base-de-donnees/decorations', label: 'Décorations', description: 'Triées par catégorie et source', icon: 'decor', dimension: 'decor', countKey: 'decors' },
    { path: '/base-de-donnees/garde-robe', label: 'Garde-robe', description: 'Apparences classées par emplacement', icon: 'transmog', dimension: 'transmog', countKey: 'appearances' },
    { path: '/base-de-donnees/professions', label: 'Professions', description: 'Recettes classées par extension', icon: 'professions', dimension: 'professions', countKey: 'recipes' },
];

defineProps({
    meta: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
});

const { readable } = useWowColor();

const colorOf = (section) => dimensionColor(section.dimension);
const formatCount = (count) => (count ? count.toLocaleString('fr-FR') : '—');
</script>
