<template>
    <div class="space-y-6">
        <Head>
            <title>{{ current.title }}</title>
            <meta name="robots" content="noindex, nofollow">
        </Head>

        <h1 class="font-display text-3xl font-bold text-default">Mon compte</h1>

        <Tabs v-model="activeView" :tabs="VIEWS" label="Vues du compte">
            <template #personnages>
                <CharactersView />
            </template>
            <template #score>
                <ScoreView />
            </template>
            <template #classes>
                <ClassesView />
            </template>
        </Tabs>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Tabs from '../components/ui/Tabs.vue';
import CharactersView from '../components/account/CharactersView.vue';
import ClassesView from '../components/account/ClassesView.vue';
import ScoreView from '../components/account/ScoreView.vue';

// Mirror of AccountController::VIEWS_BY_SEGMENT: the characters are the base address.
const VIEWS = Object.freeze([
    { value: 'personnages', label: 'Personnages', url: '/mon-compte', title: 'Mes personnages - WowPlanet' },
    { value: 'score', label: 'Score du compte', url: '/mon-compte/score', title: 'Score du compte - WowPlanet' },
    { value: 'classes', label: 'Classes', url: '/mon-compte/classes', title: 'Mes classes - WowPlanet' },
]);

const props = defineProps({
    view: { type: String, default: 'personnages' },
});

const current = computed(() => VIEWS.find((view) => view.value === props.view) ?? VIEWS[0]);

// Switching view rewrites the address and the history without a request.
const activeView = computed({
    get: () => current.value.value,
    set: (value) => {
        const target = VIEWS.find((view) => view.value === value);
        router.push({ url: target.url, props: (page) => ({ ...page, view: value }), preserveState: true, preserveScroll: true });
    },
});
</script>
