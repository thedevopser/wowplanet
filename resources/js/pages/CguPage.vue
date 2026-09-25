<template>
    <EditorialPage :sections="SECTIONS" :title="TITLE" updated-at="2026-02-20">
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

        <EditorialNotice>
            WowPlanet est un site fan non officiel. Il n'est en aucun cas li&eacute;, affili&eacute; ou approuv&eacute; par Blizzard Entertainment, Inc. World of Warcraft et Blizzard Entertainment sont des marques d&eacute;pos&eacute;es de Blizzard Entertainment, Inc.
        </EditorialNotice>

        <section id="objet">
            <h2>1. Objet</h2>
            <p>
                Les pr&eacute;sentes Conditions G&eacute;n&eacute;rales d'Utilisation (ci-apr&egrave;s &laquo;&nbsp;CGU&nbsp;&raquo;) ont pour objet de d&eacute;finir les modalit&eacute;s
                d'acc&egrave;s et d'utilisation du site <strong>wowplanet.fr</strong> (ci-apr&egrave;s &laquo;&nbsp;WowPlanet&nbsp;&raquo;).
                En acc&eacute;dant au site, vous acceptez sans r&eacute;serve les pr&eacute;sentes CGU.
            </p>
        </section>

        <section id="description-du-service">
            <h2>2. Description du service</h2>
            <p>
                WowPlanet est un outil gratuit de suivi de progression pour le jeu World of Warcraft.
                Le service permet aux utilisateurs de&nbsp;:
            </p>
            <ul>
                <li>Consulter la progression de leurs personnages (qu&ecirc;tes, hauts-faits, montures, mascottes, m&eacute;tiers)</li>
                <li>Comparer leurs accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu</li>
                <li>Consulter les classements PvP de la saison en cours</li>
            </ul>
            <p>
                Les donn&eacute;es de jeu sont r&eacute;cup&eacute;r&eacute;es exclusivement via l'API officielle de Blizzard Entertainment.
            </p>
        </section>

        <section id="acces-au-service">
            <h2>3. Acc&egrave;s au service</h2>
            <p>
                L'acc&egrave;s au site est gratuit et ouvert &agrave; tous. Certaines fonctionnalit&eacute;s n&eacute;cessitent une connexion
                via Battle.net OAuth. WowPlanet se r&eacute;serve le droit de suspendre ou d'interrompre le service
                &agrave; tout moment, sans pr&eacute;avis ni indemnit&eacute;.
            </p>
        </section>

        <section id="propriete-intellectuelle">
            <h2>4. Propri&eacute;t&eacute; intellectuelle</h2>
            <p>
                World of Warcraft, Blizzard Entertainment, Battle.net et tous les noms, personnages, images et
                &eacute;l&eacute;ments associ&eacute;s sont des marques d&eacute;pos&eacute;es et/ou des propri&eacute;t&eacute;s de Blizzard Entertainment, Inc.
            </p>
            <p>
                WowPlanet est un <strong>site fan non officiel</strong> cr&eacute;&eacute; par des passionn&eacute;s.
                Il n'est en aucun cas li&eacute;, affili&eacute;, approuv&eacute; ou sponsoris&eacute; par Blizzard Entertainment, Inc.
                L'utilisation des noms, images et donn&eacute;es du jeu est faite dans le cadre du
                <em>fair use</em> et des conditions d'utilisation de l'API Blizzard.
            </p>
            <p>
                Le code source, le design et les contenus originaux du site WowPlanet sont la propri&eacute;t&eacute; de WowPlanet
                et ne peuvent &ecirc;tre reproduits sans autorisation.
            </p>
        </section>

        <section id="responsabilite-et-limitations">
            <h2>5. Responsabilit&eacute; et limitations</h2>
            <p>
                WowPlanet s'efforce de fournir des informations exactes et &agrave; jour, mais ne saurait &ecirc;tre tenu responsable&nbsp;:
            </p>
            <ul>
                <li>Des erreurs ou omissions dans les donn&eacute;es affich&eacute;es</li>
                <li>De l'indisponibilit&eacute; temporaire ou permanente de l'API Blizzard</li>
                <li>Des dommages directs ou indirects li&eacute;s &agrave; l'utilisation du site</li>
                <li>Du contenu des sites tiers vers lesquels des liens sont propos&eacute;s (Wowhead, Blizzard, etc.)</li>
            </ul>
        </section>

        <section id="donnees-personnelles">
            <h2>6. Donn&eacute;es personnelles</h2>
            <p>
                Le traitement des donn&eacute;es personnelles est d&eacute;crit dans notre
                <Link href="/privacy">Politique de confidentialit&eacute;</Link>.
                En utilisant le site, vous reconnaissez avoir pris connaissance de cette politique.
            </p>
        </section>

        <section id="comportement-des-utilisateurs">
            <h2>7. Comportement des utilisateurs</h2>
            <p>
                Les utilisateurs s'engagent &agrave; ne pas utiliser le site de mani&egrave;re abusive, notamment&nbsp;:
            </p>
            <ul>
                <li>Ne pas tenter d'acc&eacute;der de mani&egrave;re non autoris&eacute;e aux syst&egrave;mes du site</li>
                <li>Ne pas surcharger le service par des requ&ecirc;tes automatis&eacute;es excessives</li>
                <li>Ne pas utiliser le site &agrave; des fins ill&eacute;gales</li>
            </ul>
        </section>

        <section id="modification-des-cgu">
            <h2>8. Modification des CGU</h2>
            <p>
                WowPlanet se r&eacute;serve le droit de modifier les pr&eacute;sentes CGU &agrave; tout moment.
                Les modifications prennent effet d&egrave;s leur publication sur le site.
                L'utilisation continue du site apr&egrave;s modification vaut acceptation des nouvelles CGU.
            </p>
        </section>

        <section id="droit-applicable">
            <h2>9. Droit applicable</h2>
            <p>
                Les pr&eacute;sentes CGU sont soumises au droit fran&ccedil;ais.
                En cas de litige, les parties s'efforceront de trouver une solution amiable.
                &Agrave; d&eacute;faut, les tribunaux fran&ccedil;ais seront seuls comp&eacute;tents.
            </p>
        </section>

        <section id="contact">
            <h2>10. Contact</h2>
            <p>
                Pour toute question concernant les pr&eacute;sentes CGU, vous pouvez nous contacter via notre
                <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer">serveur Discord</a>.
            </p>
        </section>
    </EditorialPage>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import EditorialNotice from '../components/EditorialNotice.vue';
import EditorialPage from '../components/EditorialPage.vue';

const TITLE = "Conditions Générales d'Utilisation";

const SECTIONS = [
    { id: 'objet', title: 'Objet' },
    { id: 'description-du-service', title: 'Description du service' },
    { id: 'acces-au-service', title: 'Accès au service' },
    { id: 'propriete-intellectuelle', title: 'Propriété intellectuelle' },
    { id: 'responsabilite-et-limitations', title: 'Responsabilité et limitations' },
    { id: 'donnees-personnelles', title: 'Données personnelles' },
    { id: 'comportement-des-utilisateurs', title: 'Comportement des utilisateurs' },
    { id: 'modification-des-cgu', title: 'Modification des CGU' },
    { id: 'droit-applicable', title: 'Droit applicable' },
    { id: 'contact', title: 'Contact' },
];

defineProps({
    meta: { type: Object, required: true },
});
</script>
