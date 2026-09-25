<template>
    <EditorialPage :sections="SECTIONS" title="Foire aux questions" lead="Tout ce que vous devez savoir pour profiter pleinement de WowPlanet.">
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

        <!-- Q1 : Qu'est-ce que WowPlanet ? -->
        <section id="quest-ce-que-wowplanet">
            <h2>Qu&rsquo;est-ce que WowPlanet&nbsp;?</h2>
            <p>
                WowPlanet est un <strong>site fan gratuit et non officiel</strong> de suivi de progression
                pour World of Warcraft. Il vous permet de visualiser en d&eacute;tail la progression de vos personnages
                et de comparer vos accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu &mdash; enti&egrave;rement en fran&ccedil;ais.
            </p>
            <p>
                Les donn&eacute;es sont issues de l&rsquo;API officielle Blizzard et couvrent&nbsp;:
            </p>
            <ul class="columns-2 sm:columns-3">
                <li>
                    Montures (1&nbsp;500+)
                </li>
                <li>
                    Mascottes (2&nbsp;100+)
                </li>
                <li>
                    Hauts-faits (8&nbsp;600+)
                </li>
                <li>
                    Qu&ecirc;tes (21&nbsp;000+)
                </li>
                <li>
                    D&eacute;corations
                </li>
                <li>
                    M&eacute;tiers &amp; R&eacute;putations
                </li>
            </ul>

        </section>

        <!-- Q2 : Comment importer mes personnages ? -->
        <section id="comment-importer-mes-personnages">
            <h2>Comment importer mes personnages&nbsp;?</h2>
            <p>
                Cliquez sur <strong>&laquo;&nbsp;Se connecter avec Battle.net&nbsp;&raquo;</strong> en haut du site.
                Une fois authentifi&eacute;, <strong>tous vos personnages</strong> sont automatiquement import&eacute;s et synchronis&eacute;s.
                Aucune configuration manuelle n&rsquo;est n&eacute;cessaire.
            </p>
            <EditorialNotice tone="info">
                    <strong>Cross-personnage&nbsp;:</strong>
                    Les hauts-faits et les r&eacute;putations sont agr&eacute;g&eacute;s au niveau du compte (<em>account-wide</em>).
                    Pour les qu&ecirc;tes et les recettes de m&eacute;tiers, WowPlanet vous indique sur quel autre personnage
                    un &eacute;l&eacute;ment est d&eacute;j&agrave; compl&eacute;t&eacute; gr&acirc;ce &agrave; la mention
                    <strong>&laquo;&nbsp;disponible sur un autre personnage&nbsp;&raquo;</strong>.
                </EditorialNotice>

        </section>

        <!-- Q3 : Qu'est-ce que le score compte ? -->
        <section id="quest-ce-que-le-score">
            <h2>Qu&rsquo;est-ce que le score compte&nbsp;?</h2>
            <p>
                Le score compte est une <strong>note globale sur 100</strong> qui &eacute;value votre progression
                sur l&rsquo;ensemble de vos personnages. Depuis la <strong>formule v2</strong>, il est calcul&eacute;
                sur <strong>9&nbsp;dimensions</strong>&nbsp;:
            </p>
            <ul class="columns-2 sm:columns-3">
                <li>Qu&ecirc;tes</li>
                <li>Hauts-faits</li>
                <li>R&eacute;putations</li>
                <li>Raids</li>
                <li>Montures</li>
                <li>Garde-robe</li>
                <li>Mascottes</li>
                <li>D&eacute;corations</li>
                <li>M&eacute;tiers</li>
            </ul>
            <p>
                La <strong>garde-robe</strong> et les <strong>raids</strong> sont arriv&eacute;s
                avec la v2&nbsp;: si votre note a baiss&eacute;, c&rsquo;est que la barre est simplement mont&eacute;e. Les raids sont
                not&eacute;s &agrave; la <strong>difficult&eacute; maximale atteinte</strong>, boss par boss&nbsp;:
                un raid termin&eacute; en normal vaut la moiti&eacute; du m&ecirc;me raid termin&eacute; en mythique.
            </p>
            <p>
                Une dimension <strong>sans aucune donn&eacute;e</strong> — vous ne raidez pas, vous n&rsquo;avez pas
                de m&eacute;tier — ne compte pas pour z&eacute;ro&nbsp;: elle sort du calcul et son poids est redistribu&eacute;.
                Le <strong>PvP n&rsquo;entre pas</strong> dans le score&nbsp;: il ne concerne qu&rsquo;une partie des
                joueurs et p&eacute;naliserait injustement les profils PvE. Il reste consultable dans son onglet et dans les classements.
            </p>
            <p>
                Le r&eacute;sultat est visualis&eacute; sous forme de <strong>radar chart</strong> et peut &ecirc;tre
                <strong>partag&eacute; sur Discord</strong>.
                Acc&eacute;dez-y apr&egrave;s connexion depuis <strong>Mon compte</strong>, onglet <strong>&laquo;&nbsp;Score du compte&nbsp;&raquo;</strong>.
            </p>

        </section>

        <!-- Q4 : Peut-on utiliser le site sans se connecter ? -->
        <section id="peut-on-utiliser-le-site">
            <h2>Peut-on utiliser le site sans se connecter&nbsp;?</h2>
            <p>
                <strong>Oui, absolument.</strong> Plusieurs fonctionnalit&eacute;s sont accessibles sans connexion Battle.net&nbsp;:
            </p>
            <ul>
                <li>
                    La <a href="/base-de-donnees">base de donn&eacute;es compl&egrave;te</a> (montures, hauts-faits, qu&ecirc;tes, mascottes, d&eacute;corations, professions)
                </li>
                <li>
                    Les <a href="/classements-pvp">classements PvP</a> de la saison en cours
                </li>
                <li>
                    La fiche publique d&rsquo;un personnage, depuis le lien de sa fiche ou depuis les classements PvP
                </li>
            </ul>
            <p>
                La connexion Battle.net est n&eacute;cessaire uniquement pour importer vos propres personnages,
                acc&eacute;der au score compte et g&eacute;rer vos t&acirc;ches.
            </p>

        </section>

        <!-- Q5 : Comment fonctionnent les tâches quotidiennes ? -->
        <section id="comment-fonctionnent-les-taches-quotidiennes">
            <h2>Comment fonctionnent les t&acirc;ches quotidiennes&nbsp;?</h2>
            <p>
                WowPlanet vous permet de cr&eacute;er des <strong>t&acirc;ches personnalis&eacute;es</strong> pour chacun de vos personnages
                afin de suivre vos objectifs r&eacute;currents (coffres de cl&eacute;s mythiques, qu&ecirc;tes mondiales, etc.).
            </p>
            <ul>
                <li>
                    <strong>Quotidiennes</strong>&nbsp;: R&eacute;initialis&eacute;es automatiquement chaque jour &agrave; 5h du matin
                </li>
                <li>
                    <strong>Hebdomadaires</strong>&nbsp;: R&eacute;initialis&eacute;es automatiquement chaque mercredi &agrave; 5h du matin
                </li>
                <li>
                    <strong>Mensuelles</strong>&nbsp;: R&eacute;initialis&eacute;es automatiquement le 1er de chaque mois &agrave; 5h du matin
                </li>
            </ul>
            <p>
                Acc&eacute;dez &agrave; vos t&acirc;ches via le <strong>bouton en bas &agrave; droite</strong> de l&rsquo;&eacute;cran.
                Un compteur vous indique le nombre de t&acirc;ches en attente.
            </p>

        </section>

        <template #wide>
            <div class="mx-auto max-w-3xl space-y-3">
                <p class="text-muted">Une question qui n&rsquo;est pas list&eacute;e ici&nbsp;?</p>
                <DiscordInvite />
            </div>
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
import DiscordInvite from '../components/DiscordInvite.vue';
import EditorialNotice from '../components/EditorialNotice.vue';
import EditorialPage from '../components/EditorialPage.vue';

const SECTIONS = [
    { id: 'quest-ce-que-wowplanet', title: 'Qu’est-ce que WowPlanet ?' },
    { id: 'comment-importer-mes-personnages', title: 'Comment importer mes personnages ?' },
    { id: 'quest-ce-que-le-score', title: 'Qu’est-ce que le score compte ?' },
    { id: 'peut-on-utiliser-le-site', title: 'Peut-on utiliser le site sans se connecter ?' },
    { id: 'comment-fonctionnent-les-taches-quotidiennes', title: 'Comment fonctionnent les tâches quotidiennes ?' },
];

defineProps({
    meta: { type: Object, required: true },
});
</script>
