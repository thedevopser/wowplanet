<template>
    <EditorialPage :sections="sections" :title="$t('faq.title')" :lead="$t('faq.lead')">
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

        <template #wide>
            <div class="mx-auto max-w-3xl space-y-3">
                <p class="text-muted">{{ $t('faq.more') }}</p>
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
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DiscordInvite from '../components/DiscordInvite.vue';
import EditorialPage from '../components/EditorialPage.vue';
import FaqContentEn from './content/FaqContentEn.vue';
import FaqContentFr from './content/FaqContentFr.vue';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useTranslate } from '../composables/useTranslate';

const SECTION_IDS = [
    'quest-ce-que-wowplanet',
    'comment-importer-mes-personnages',
    'quest-ce-que-le-score',
    'peut-on-utiliser-le-site',
    'comment-fonctionnent-les-taches-quotidiennes',
];

const t = useTranslate();
const locale = useActiveLocale();
const sections = computed(() => SECTION_IDS.map((id) => ({ id, title: t(`faq.sections.${id}`) })));
const content = computed(() => (locale.value === 'en' ? FaqContentEn : FaqContentFr));

defineProps({
    meta: { type: Object, required: true },
});
</script>
