<template>
    <EditorialPage :sections="sections" :title="$t('legal.privacy.title')" updated-at="2026-11-22">
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

        <component :is="content" />
    </EditorialPage>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import EditorialPage from '../components/EditorialPage.vue';
import PrivacyContentEn from './content/PrivacyContentEn.vue';
import PrivacyContentFr from './content/PrivacyContentFr.vue';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useTranslate } from '../composables/useTranslate';

const SECTION_IDS = [
    'responsable-du-traitement',
    'donnees-collectees',
    'base-legale-et-finalites',
    'duree-de-conservation',
    'vos-droits-rgpd',
    'cookies',
    'mesure-daudience',
    'infobulles-wowhead',
    'transfert-de-donnees',
    'securite',
];

const t = useTranslate();
const locale = useActiveLocale();
const sections = computed(() => SECTION_IDS.map((id) => ({ id, title: t(`legal.privacy.sections.${id}`) })));
const content = computed(() => (locale.value === 'en' ? PrivacyContentEn : PrivacyContentFr));

defineProps({
    meta: { type: Object, required: true },
});
</script>
