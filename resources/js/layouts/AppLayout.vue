<template>
    <div class="flex min-h-screen flex-col font-sans selection:bg-accent/30">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-toast focus:rounded-ui-md focus:bg-accent focus:px-4 focus:py-2 focus:text-on-accent focus:shadow-elevation-2">
            {{ $t('nav.skip_to_content') }}
        </a>

        <AppHeaderInertia />
        <LocaleSuggestionBanner />

        <main id="main-content" class="flex-1" :class="isDatabase ? 'flex' : ''">
            <div v-if="isDatabase" class="flex min-h-0 min-w-0 flex-1">
                <slot />
            </div>
            <div v-else class="mx-auto w-full max-w-7xl px-4 py-6 sm:py-8 md:px-6">
                <slot />
            </div>
        </main>

        <AppFooterInertia />

        <TaskSidebarInertia v-if="store.isAuthenticated" />
        <ToastStack :offset="store.isAuthenticated ? 'above-fab' : 'default'" />
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCharacterStore } from '../stores/character';
import { useTaskStore } from '../stores/tasks';
import { startThemeSync } from '../composables/useTheme';
import { DEFAULT_LOCALE, localizePath } from '../i18n/localizePath';
import AppHeaderInertia from '../components/inertia/AppHeaderInertia.vue';
import LocaleSuggestionBanner from '../components/inertia/LocaleSuggestionBanner.vue';
import AppFooterInertia from '../components/inertia/AppFooterInertia.vue';
import TaskSidebarInertia from '../components/inertia/TaskSidebarInertia.vue';
import ToastStack from '../components/ui/ToastStack.vue';
import { useToastStore } from '../stores/toasts';
import { watchFlashMessages } from '../composables/useFlashToasts';
import { loginUrl } from '../utils/auth';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useTranslate } from '../composables/useTranslate';

const page = usePage();
const store = useCharacterStore();
const taskStore = useTaskStore();
const toasts = useToastStore();
const t = useTranslate();
const locale = useActiveLocale();
const flashOptions = { t, get loginUrl() { return loginUrl(locale.value); } };

const isDatabase = computed(() => localizePath(page.url.split('?')[0], DEFAULT_LOCALE).startsWith('/base-de-donnees'));

watch(() => page.props.auth, (auth) => store.applySharedAuth(auth), { immediate: true });

watch(() => store.error, (error) => {
    if (error) {
        toasts.show({ title: t(error), tone: 'error' });
    }
});

watch(() => store.sessionExpired, (expired) => {
    if (!expired) {
        return;
    }

    toasts.show({ title: t('flash.session_expired.title'), tone: 'warning', action: { label: t('flash.session_expired.action'), href: loginUrl(locale.value) } });
    store.clearSessionExpired();
});

watch(() => store.isAuthenticated, (authenticated) => {
    if (authenticated) {
        taskStore.fetchTasks();
    }
});

onMounted(() => {
    startThemeSync();
    watchFlashMessages(page, toasts, flashOptions);
    if (store.isAuthenticated) {
        taskStore.fetchTasks();
    }
});
</script>
