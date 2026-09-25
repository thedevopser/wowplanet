<template>
    <EditorialPage :sections="SECTIONS" :title="TITLE" updated-at="2026-09-25">
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

        <section id="responsable-du-traitement">
            <h2>1. Responsable du traitement</h2>
            <p>
                Le site <strong>wowplanet.fr</strong> (ci-apr&egrave;s &laquo;&nbsp;WowPlanet&nbsp;&raquo;) est &eacute;dit&eacute; par WowPlanet.
                Pour toute question relative &agrave; la protection de vos donn&eacute;es, vous pouvez nous contacter via notre
                <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer">serveur Discord</a>.
            </p>
        </section>

        <section id="donnees-collectees">
            <h2>2. Donn&eacute;es collect&eacute;es</h2>
            <p>
                WowPlanet collecte les donn&eacute;es suivantes lorsque vous vous connectez via Battle.net&nbsp;:
            </p>
            <ul>
                <li>Votre identifiant Battle.net (BattleTag)</li>
                <li>Votre identifiant num&eacute;rique Battle.net</li>
                <li>La liste de vos personnages World of Warcraft et leurs donn&eacute;es de progression (qu&ecirc;tes, hauts-faits, montures, mascottes, m&eacute;tiers)</li>
            </ul>
            <p>
                Ces donn&eacute;es sont r&eacute;cup&eacute;r&eacute;es exclusivement via l'API officielle de Blizzard Entertainment.
                Aucune donn&eacute;e suppl&eacute;mentaire (adresse e-mail, mot de passe, informations de paiement) n'est collect&eacute;e par WowPlanet.
            </p>
        </section>

        <section id="base-legale-et-finalites">
            <h2>3. Base l&eacute;gale et finalit&eacute;s</h2>
            <p>
                Le traitement de vos donn&eacute;es repose sur votre <strong>consentement</strong>, manifest&eacute; par votre connexion volontaire via Battle.net OAuth.
                Les donn&eacute;es sont utilis&eacute;es uniquement pour&nbsp;:
            </p>
            <ul>
                <li>Afficher votre progression dans World of Warcraft</li>
                <li>Comparer vos accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu</li>
                <li>Permettre la recherche de personnages</li>
            </ul>
        </section>

        <section id="duree-de-conservation">
            <h2>4. Dur&eacute;e de conservation</h2>
            <p>
                Vos donn&eacute;es de session sont conserv&eacute;es pendant la dur&eacute;e de votre session de navigation.
                Les donn&eacute;es de personnages sont mises en cache temporairement pour am&eacute;liorer les performances du service.
                Vous pouvez &agrave; tout moment demander la suppression de vos donn&eacute;es.
            </p>
        </section>

        <section id="vos-droits-rgpd">
            <h2>5. Vos droits (RGPD)</h2>
            <p>
                Conform&eacute;ment au R&egrave;glement G&eacute;n&eacute;ral sur la Protection des Donn&eacute;es (RGPD), vous disposez des droits suivants&nbsp;:
            </p>
            <ul>
                <li><strong>Droit d'acc&egrave;s</strong>&nbsp;: obtenir une copie de vos donn&eacute;es personnelles</li>
                <li><strong>Droit de rectification</strong>&nbsp;: corriger des donn&eacute;es inexactes</li>
                <li><strong>Droit de suppression</strong>&nbsp;: demander l'effacement de vos donn&eacute;es</li>
                <li><strong>Droit &agrave; la portabilit&eacute;</strong>&nbsp;: recevoir vos donn&eacute;es dans un format structur&eacute;</li>
                <li><strong>Droit d'opposition</strong>&nbsp;: vous opposer au traitement de vos donn&eacute;es</li>
                <li><strong>Droit de retrait du consentement</strong>&nbsp;: retirer votre consentement &agrave; tout moment</li>
            </ul>
            <p>
                Pour exercer ces droits, contactez-nous via notre
                <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer">serveur Discord</a>.
                Vous disposez &eacute;galement du droit d'introduire une r&eacute;clamation aupr&egrave;s de la
                <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">CNIL</a>.
            </p>
        </section>

        <section id="cookies">
            <h2>6. Cookies</h2>
            <p>
                WowPlanet utilise uniquement des cookies strictement n&eacute;cessaires au fonctionnement du service
                (cookie de session, jeton CSRF), ainsi qu'un cookie de pr&eacute;f&eacute;rence de th&egrave;me, <code>wowplanet-theme</code>,
                qui retient pendant un an votre choix entre th&egrave;me clair, sombre ou celui de votre syst&egrave;me, afin d'afficher
                chaque page directement dans ce th&egrave;me. Aucun cookie publicitaire ou de tra&ccedil;age n'est utilis&eacute;.
                Ces cookies ne n&eacute;cessitent pas votre consentement pr&eacute;alable conform&eacute;ment &agrave; la l&eacute;gislation en vigueur.
            </p>
        </section>

        <section id="mesure-daudience">
            <h2>7. Mesure d&rsquo;audience</h2>
            <p>
                WowPlanet mesure sa fr&eacute;quentation avec <strong>Umami</strong>, un outil de mesure d&rsquo;audience h&eacute;berg&eacute;
                par WowPlanet lui-m&ecirc;me (<code>umami.wowplanet.fr</code>). Il ne d&eacute;pose aucun cookie et ne suit pas votre navigation
                d&rsquo;un site &agrave; l&rsquo;autre&nbsp;: il compte les pages vues de fa&ccedil;on agr&eacute;g&eacute;e (page, site d&rsquo;origine,
                navigateur, pays). Votre adresse IP sert uniquement &agrave; d&eacute;duire le pays et un identifiant de visite anonyme,
                et n&rsquo;est pas conserv&eacute;e. Ces statistiques ne sont ni revendues ni partag&eacute;es.
            </p>
        </section>

        <section id="infobulles-wowhead">
            <h2>8. Infobulles Wowhead</h2>
            <p>
                Pour afficher la fiche d&rsquo;un objet, d&rsquo;une monture ou d&rsquo;un haut-fait au survol d&rsquo;un lien, WowPlanet charge
                le script d&rsquo;infobulles du site <strong>Wowhead</strong>, depuis <code>wow.zamimg.com</code>. Comme pour tout contenu
                charg&eacute; depuis un autre site, votre navigateur transmet alors &agrave; Wowhead votre <strong>adresse IP</strong> et des
                informations techniques (navigateur, page consult&eacute;e). Ces donn&eacute;es sont trait&eacute;es par Wowhead selon sa propre
                politique de confidentialit&eacute;, sur laquelle WowPlanet n&rsquo;a pas la main.
            </p>
        </section>

        <section id="transfert-de-donnees">
            <h2>9. Transfert de donn&eacute;es</h2>
            <p>
                Les donn&eacute;es de progression sont r&eacute;cup&eacute;r&eacute;es depuis les serveurs de Blizzard Entertainment
                via leur API officielle. En dehors de cette interaction et du chargement des infobulles Wowhead d&eacute;crit ci-dessus,
                WowPlanet ne transmet aucune donn&eacute;e personnelle &agrave; des tiers.
            </p>
        </section>

        <section id="securite">
            <h2>10. S&eacute;curit&eacute;</h2>
            <p>
                WowPlanet met en &oelig;uvre des mesures techniques et organisationnelles pour prot&eacute;ger vos donn&eacute;es personnelles
                contre tout acc&egrave;s non autoris&eacute;, perte ou alt&eacute;ration. Le site utilise le protocole HTTPS pour chiffrer les &eacute;changes.
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
import { Head } from '@inertiajs/vue3';
import EditorialNotice from '../components/EditorialNotice.vue';
import EditorialPage from '../components/EditorialPage.vue';

const TITLE = 'Politique de confidentialité';

const SECTIONS = [
    { id: 'responsable-du-traitement', title: 'Responsable du traitement' },
    { id: 'donnees-collectees', title: 'Données collectées' },
    { id: 'base-legale-et-finalites', title: 'Base légale et finalités' },
    { id: 'duree-de-conservation', title: 'Durée de conservation' },
    { id: 'vos-droits-rgpd', title: 'Vos droits (RGPD)' },
    { id: 'cookies', title: 'Cookies' },
    { id: 'mesure-daudience', title: 'Mesure d’audience' },
    { id: 'infobulles-wowhead', title: 'Infobulles Wowhead' },
    { id: 'transfert-de-donnees', title: 'Transfert de données' },
    { id: 'securite', title: 'Sécurité' },
];

defineProps({
    meta: { type: Object, required: true },
});
</script>
