<template>
    <EditorialPage :sections="sections" :title="$t('legal.terms.title')" updated-at="2026-02-20">
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
import CguContentEn from './content/CguContentEn.vue';
import CguContentFr from './content/CguContentFr.vue';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useTranslate } from '../composables/useTranslate';

const SECTION_IDS = [
    'objet',
    'description-du-service',
    'acces-au-service',
    'propriete-intellectuelle',
    'responsabilite-et-limitations',
    'donnees-personnelles',
    'comportement-des-utilisateurs',
    'modification-des-cgu',
    'droit-applicable',
    'contact',
];

const t = useTranslate();
const locale = useActiveLocale();
const sections = computed(() => SECTION_IDS.map((id) => ({ id, title: t(`legal.terms.sections.${id}`) })));
const content = computed(() => (locale.value === 'en' ? CguContentEn : CguContentFr));

defineProps({
    meta: { type: Object, required: true },
});
</script>
