<template>
    <div class="space-y-6">
        <Head>
            <title>{{ $t(current.title) }}</title>
            <meta name="robots" content="noindex, nofollow">
        </Head>

        <h1 class="font-display text-3xl font-bold text-default">{{ $t('nav.account') }}</h1>

        <Tabs v-model="activeView" :tabs="tabs" :label="$t('account.views.label')">
            <template #personnages>
                <CharactersView />
            </template>
            <template #a-faire>
                <TodoView />
            </template>
            <template #semaine>
                <WeekView />
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
import { useLocalizedUrl } from '../composables/useLocalizedUrl';
import { useTranslate } from '../composables/useTranslate';
import CharactersView from '../components/account/CharactersView.vue';
import ClassesView from '../components/account/ClassesView.vue';
import ScoreView from '../components/account/ScoreView.vue';
import TodoView from '../components/account/TodoView.vue';
import WeekView from '../components/account/WeekView.vue';

// Mirror of AccountController::VIEWS_BY_SEGMENT: the characters are the base address. The
// segments stay the same in both languages; labels and titles are translation keys.
const VIEWS = Object.freeze([
    { value: 'personnages', label: 'account.views.characters', url: '/mon-compte', title: 'account.views.characters_title' },
    { value: 'a-faire', label: 'account.views.todo', url: '/mon-compte/a-faire', title: 'account.views.todo_title' },
    { value: 'semaine', label: 'account.views.week', url: '/mon-compte/semaine', title: 'account.views.week_title' },
    { value: 'score', label: 'score.account_title', url: '/mon-compte/score', title: 'account.views.score_title' },
    { value: 'classes', label: 'account.views.classes', url: '/mon-compte/classes', title: 'account.views.classes_title' },
]);

const t = useTranslate();
const { localizedUrl } = useLocalizedUrl();
const tabs = computed(() => VIEWS.map((view) => ({ ...view, label: t(view.label) })));

const props = defineProps({
    view: { type: String, default: 'personnages' },
});

const current = computed(() => VIEWS.find((view) => view.value === props.view) ?? VIEWS[0]);

// Switching view rewrites the address and the history without a request.
const activeView = computed({
    get: () => current.value.value,
    set: (value) => {
        const target = VIEWS.find((view) => view.value === value);
        router.push({ url: localizedUrl(target.url), props: (page) => ({ ...page, view: value }), preserveState: true, preserveScroll: true });
    },
});
</script>
