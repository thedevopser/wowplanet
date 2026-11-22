<template>
    <div
        v-if="suggestion && !dismissed"
        role="region"
        :lang="suggestion"
        :aria-label="$t(`locale.suggest.${suggestion}.label`)"
        class="border-b border-default bg-surface-raised"
    >
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2 md:px-6">
            <p class="text-sm text-default">{{ $t(`locale.suggest.${suggestion}.text`) }}</p>
            <div class="flex items-center gap-2">
                <Button variant="primary" size="sm" :href="suggestedUrl" external :hreflang="suggestion" @click="rememberLocale(suggestion)">
                    {{ $t(`locale.suggest.${suggestion}.action`) }}
                </Button>
                <Button variant="ghost" size="sm" @click="keepPageLocale">
                    {{ $t(`locale.suggest.${suggestion}.dismiss`) }}
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '../ui/Button.vue';
import { rememberLocale } from '../../i18n/localeChoice';

const page = usePage();
const dismissed = ref(false);
const suggestion = computed(() => page.props.localeSuggestion ?? null);
const suggestedUrl = computed(() => page.props.alternateUrls?.[suggestion.value] ?? '');

function keepPageLocale() {
    rememberLocale(page.props.locale);
    dismissed.value = true;
}
</script>
