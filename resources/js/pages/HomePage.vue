<template>
    <div data-testid="homepage-root" class="space-y-12 py-8 sm:space-y-16 sm:py-12">
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

        <section class="grid gap-8 lg:grid-cols-[3fr_2fr] lg:items-center">
            <div class="space-y-6">
                <p class="flex items-center gap-3">
                    <img src="/images/logo.png" alt="" width="40" height="40" class="size-10 rounded-ui-md border border-default object-cover">
                    <span class="font-display text-lg font-semibold text-default">WowPlanet</span>
                </p>
                <h1 class="font-display text-4xl font-bold leading-tight text-default sm:text-5xl">Suivez votre progression World of Warcraft</h1>
                <p class="max-w-prose text-lg leading-relaxed text-muted">
                    WowPlanet analyse votre personnage World of Warcraft via l’API Blizzard et compare vos accomplissements avec la base de données complète du jeu.
                    Quêtes, hauts-faits, montures, mascottes, décorations, garde-robe : visualisez tout ce qu’il vous reste à accomplir.
                </p>
                <div data-hero-actions class="flex flex-wrap gap-3">
                    <Button v-if="isAuthenticated" href="/mon-compte" variant="primary">
                        <Icon :icon="UserRound" size="sm" />
                        Mon compte
                    </Button>
                    <Button v-else :href="LOGIN_URL" external variant="primary">
                        <Icon :icon="LogIn" size="sm" />
                        Se connecter avec Battle.net
                    </Button>
                    <Button href="/base-de-donnees">
                        <Icon :icon="Library" size="sm" />
                        Explorer la base de données
                    </Button>
                </div>
                <p v-if="!isAuthenticated" class="text-sm text-subtle">Connexion officielle Battle.net : aucun mot de passe ne transite par WowPlanet.</p>
            </div>

            <dl class="grid grid-cols-2 gap-3">
                <div
                    v-for="item in TRACKED"
                    :key="item.countKey"
                    data-tracked
                    class="rounded-ui-md border border-l-4 border-default bg-surface p-4"
                    :style="{ borderLeftColor: dimensionColor(item.dimension).base }"
                >
                    <dt class="text-sm text-muted">{{ item.label }}</dt>
                    <dd class="text-2xl font-bold tabular-nums text-default">{{ formatCount(counts[item.countKey]) }}</dd>
                    <dd class="text-xs text-subtle">{{ item.detail }}</dd>
                </div>
            </dl>
        </section>

        <section data-explore aria-labelledby="explore-heading" class="space-y-4">
            <h2 id="explore-heading" class="font-display text-2xl font-semibold text-default">Explorer la base de données</h2>
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    v-for="section in SECTIONS"
                    :key="section.path"
                    as="li"
                    variant="interactive"
                    class="flex items-center gap-3 border-t-4 p-4"
                    :style="{ borderTopColor: dimensionColor(section.dimension).base }"
                >
                    <span class="size-9 shrink-0 rounded-ui-sm border border-default bg-surface-raised p-2" :style="{ color: readable(dimensionColor(section.dimension)) }">
                        <CategoryIcon :category="section.icon" />
                    </span>
                    <div class="min-w-0">
                        <Link :href="section.path" class="font-semibold text-default hover:underline focus-visible:outline-none after:absolute after:inset-0">{{ section.label }}</Link>
                        <p class="text-sm text-muted">{{ section.detail }}</p>
                    </div>
                </Card>
                <Card as="li" variant="interactive" class="flex items-center gap-3 border-t-4 border-t-accent p-4">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-ui-sm border border-default bg-surface-raised text-accent">
                        <Icon :icon="Swords" size="sm" />
                    </span>
                    <div class="min-w-0">
                        <Link href="/classements-pvp" class="font-semibold text-default hover:underline focus-visible:outline-none after:absolute after:inset-0">Classements PvP</Link>
                        <p class="text-sm text-muted">Arène, mêlée solo et blitz</p>
                    </div>
                </Card>
            </ul>
        </section>

        <DiscordInvite />

        <p class="text-center text-sm text-muted">
            Données synchronisées depuis l’API officielle Blizzard. Tous les noms sont en français.
            <br>Chaque élément est lié à sa fiche Wowhead pour plus de détails.
        </p>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Library, LogIn, Swords, UserRound } from 'lucide-vue-next';
import CategoryIcon from '../components/CategoryIcon.vue';
import DiscordInvite from '../components/DiscordInvite.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Icon from '../components/ui/Icon.vue';
import { useWowColor } from '../composables/useWowColor';
import { LOGIN_URL } from '../utils/auth';
import { dimensionColor } from '../utils/wowColors';

const TRACKED = [
    { label: 'Quêtes', countKey: 'quests', dimension: 'quests', detail: 'par zone et par extension' },
    { label: 'Hauts-faits', countKey: 'achievements', dimension: 'achievements', detail: 'par catégorie et par extension' },
    { label: 'Montures', countKey: 'mounts', dimension: 'mounts', detail: 'avec leur source d’obtention' },
    { label: 'Mascottes', countKey: 'pets', dimension: 'pets', detail: 'avec suivi de collection' },
];

const SECTIONS = [
    { path: '/base-de-donnees/montures', label: 'Montures', detail: 'Par catégorie et source', icon: 'mounts', dimension: 'mounts' },
    { path: '/base-de-donnees/hauts-faits', label: 'Hauts-faits', detail: 'Par extension et catégorie', icon: 'achievements', dimension: 'achievements' },
    { path: '/base-de-donnees/quetes', label: 'Quêtes', detail: 'Par extension et zone', icon: 'quests', dimension: 'quests' },
    { path: '/base-de-donnees/mascottes', label: 'Mascottes', detail: 'Par catégorie et source', icon: 'pets', dimension: 'pets' },
    { path: '/base-de-donnees/decorations', label: 'Décorations', detail: 'Par catégorie et source', icon: 'decor', dimension: 'decor' },
    { path: '/base-de-donnees/garde-robe', label: 'Garde-robe', detail: 'Apparences par emplacement', icon: 'transmog', dimension: 'transmog' },
    { path: '/base-de-donnees/professions', label: 'Professions', detail: 'Recettes par extension', icon: 'professions', dimension: 'professions' },
];

defineProps({
    meta: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
});

const page = usePage();
const { readable } = useWowColor();

const isAuthenticated = computed(() => page.props.auth?.isAuthenticated ?? false);

const formatCount = (count) => (count ? count.toLocaleString('fr-FR') : '—');
</script>
