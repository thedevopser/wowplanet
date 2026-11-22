<template>
    <EditorialPage :title="$t('addons.title')" :lead="$t('addons.lead')">
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

        <template #wide>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="addon in addons" :key="addon.slug" as="li" class="flex flex-col gap-4 p-5">
                    <div class="flex items-center gap-4">
                        <img
                            :src="addon.image"
                            :alt="$t('addons.icon', { name: addon.name })"
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
                        <span>{{ $t('addons.view') }}<span class="sr-only"> {{ addon.name }}</span> {{ $t('addons.on_curseforge') }}<span class="sr-only"> {{ $t('common.new_tab') }}</span></span>
                        <Icon :icon="ExternalLink" size="sm" />
                    </Button>
                </Card>
            </ul>

            <EditorialNotice class="mt-6">
                {{ $t('addons.disclaimer') }}
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
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import EditorialNotice from '../components/EditorialNotice.vue';
import EditorialPage from '../components/EditorialPage.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Icon from '../components/ui/Icon.vue';
import { useActiveLocale } from '../composables/useActiveLocale';
import { ADDONS } from './content/addons';

defineProps({
    meta: { type: Object, required: true },
});

const locale = useActiveLocale();
const addons = computed(() => ADDONS[locale.value] ?? ADDONS.fr);
</script>
