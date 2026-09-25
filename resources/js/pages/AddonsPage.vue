<template>
    <EditorialPage title="Nos Addons" lead="Au-delà du site, nous développons des addons gratuits et open source pour World of Warcraft. Chacun est disponible sur CurseForge et maintenu à jour pour la version actuelle du jeu.">
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

        <template #wide>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="addon in addons" :key="addon.slug" as="li" class="flex flex-col gap-4 p-5">
                    <div class="flex items-center gap-4">
                        <img
                            :src="addon.image"
                            :alt="`Icône ${addon.name}`"
                            width="64"
                            height="64"
                            class="size-16 shrink-0 rounded-ui-md"
                            loading="lazy"
                        >
                        <div>
                            <h2 class="text-xl font-semibold text-default">{{ addon.name }}</h2>
                            <p class="text-sm text-muted">{{ addon.tagline }}</p>
                        </div>
                    </div>

                    <div class="editorial text-sm">
                        <p v-html="addon.description"></p>
                        <ul>
                            <li v-for="(feature, index) in addon.features" :key="index" v-html="feature"></li>
                        </ul>
                    </div>

                    <Button :href="addon.curseforge" external variant="primary" target="_blank" rel="noopener noreferrer" class="mt-auto">
                        <span>Voir<span class="sr-only"> {{ addon.name }}</span> sur CurseForge<span class="sr-only"> (nouvel onglet)</span></span>
                        <Icon :icon="ExternalLink" size="sm" />
                    </Button>
                </Card>
            </ul>

            <EditorialNotice class="mt-6">
                Ces addons sont d&eacute;velopp&eacute;s par des fans et ne sont ni li&eacute;s, ni affili&eacute;s, ni approuv&eacute;s
                par Blizzard Entertainment, Inc. World of Warcraft est une marque d&eacute;pos&eacute;e de Blizzard Entertainment, Inc.
            </EditorialNotice>
        </template>
    </EditorialPage>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import EditorialNotice from '../components/EditorialNotice.vue';
import EditorialPage from '../components/EditorialPage.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Icon from '../components/ui/Icon.vue';

defineProps({
    meta: { type: Object, required: true },
});

const addons = [
    {
        slug: 'maptidy',
        name: 'MapTidy',
        tagline: 'Filtrage des marqueurs de quêtes',
        image: '/images/addons/maptidy.png',
        curseforge: 'https://www.curseforge.com/wow/addons/maptidy',
        description:
            'MapTidy filtre les marqueurs de quêtes sur la carte du monde et la minicarte par '
            + '<strong>type de quête</strong>. Tous les types sont visibles par défaut : '
            + 'l\'addon ne cache jamais un marqueur par erreur. Chaque filtre est mémorisé par personnage.',
        features: [
            'Affichage ou masquage des marqueurs par type de quête',
            'Option « masquer le déjà-fait par le bataillon », réglable type par type',
            'Préréglages nommés sauvegardés au niveau du compte, rappelables sur tous vos personnages',
            'Panneau redimensionnable et déplaçable (AceGUI-3.0), position mémorisée',
        ],
    },
    {
        slug: 'whattodo',
        name: 'WhatTodo',
        tagline: 'Liste de tâches à faire',
        image: '/images/addons/whattodo.png',
        curseforge: 'https://www.curseforge.com/wow/addons/whattodo',
        description:
            'WhatTodo affiche une <strong>liste de tâches</strong> qui se '
            + 'réinitialise automatiquement selon la fréquence de chaque tâche. L\'état '
            + '« fait / à faire » est persisté par personnage.',
        features: [
            'Tâches quotidiennes, hebdomadaires et mensuelles',
            'Reset automatique à 5h (heure serveur) : chaque jour, chaque mercredi, le 1er du mois',
            'État recalculé tout seul au passage de l\'heure de reset',
            'Interface en français sur les clients FR, en anglais ailleurs',
        ],
    },
    {
        slug: 'tanktruckreverse',
        name: 'TankTruckReverse',
        tagline: 'Bip de recul pour tanks',
        image: '/images/addons/tanktruckreverse.png',
        curseforge: 'https://www.curseforge.com/wow/addons/tanks-truck-reverse',
        description:
            'TankTruckReverse joue un <strong>bip de camion en marche '
            + 'arrière</strong> quand un tank recule pendant un combat. Minuscule et sans '
            + 'configuration : le son se déclenche uniquement en spécialisation tank, en '
            + 'combat, touche de recul enfoncée.',
        features: [
            'Détection fiable via les fonctions de déplacement du jeu (aucun calcul de position)',
            'Se déclenche seulement en combat, en spé tank, en reculant (le pas-chassé et la course avant ne comptent pas)',
            'Répétition du bip toutes les ~0,7 s tant que la touche de recul est maintenue',
            'Commandes : /ttr (activer/désactiver), /ttr test, /ttr debug',
        ],
    },
];
</script>
