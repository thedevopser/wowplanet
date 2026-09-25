<template>
    <div class="flex min-h-[60vh] w-full flex-col items-center justify-center gap-6 py-12 text-center">
        <Head>
            <title>Page introuvable - WowPlanet</title>
            <meta name="robots" content="noindex">
        </Head>

        <p data-code aria-hidden="true" class="font-display text-8xl font-bold text-subtle select-none sm:text-9xl">404</p>
        <div class="space-y-2">
            <h1 class="font-display text-3xl font-bold text-default">Page introuvable</h1>
            <p class="max-w-md text-muted">La page que vous recherchez n’existe pas ou a été déplacée.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-3">
            <Button href="/" variant="primary">
                <Icon :icon="House" size="sm" />
                Retour à l’accueil
            </Button>
            <Button href="/base-de-donnees">
                <Icon :icon="Library" size="sm" />
                Explorer la base de données
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
</script>
