<template>
    <div class="flex min-h-[60vh] w-full flex-col items-center justify-center gap-6 py-12 text-center">
        <Head>
            <title>{{ $t('not_found.title') }} - WowPlanet</title>
            <meta name="robots" content="noindex">
        </Head>

        <p data-code aria-hidden="true" class="font-display text-8xl font-bold text-subtle select-none sm:text-9xl">404</p>
        <div class="space-y-2">
            <h1 class="font-display text-3xl font-bold text-default">{{ $t('not_found.title') }}</h1>
            <p class="max-w-md text-muted">{{ $t('not_found.message') }}</p>
        </div>
        <div class="flex flex-wrap justify-center gap-3">
            <Button :href="localizedUrl('/')" variant="primary">
                <Icon :icon="House" size="sm" />
                {{ $t('not_found.home') }}
            </Button>
            <Button :href="localizedUrl('/base-de-donnees')">
                <Icon :icon="Library" size="sm" />
                {{ $t('home.explore_database') }}
            </Button>
        </div>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { House, Library } from 'lucide-vue-next';
import Button from '../components/ui/Button.vue';
import Icon from '../components/ui/Icon.vue';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';

const { localizedUrl } = useLocalizedUrl();
</script>
