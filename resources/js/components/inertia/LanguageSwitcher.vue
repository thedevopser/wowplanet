<template>
    <Button
        v-if="targetUrl"
        variant="ghost"
        :href="targetUrl"
        external
        :lang="target"
        :hreflang="target"
        :aria-label="$t('locale.switch')"
        @click="rememberLocale(target)"
    >
        {{ target.toUpperCase() }}
    </Button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '../ui/Button.vue';
import { rememberLocale } from '../../i18n/localeChoice';

const page = usePage();
const target = computed(() => (page.props.locale === 'en' ? 'fr' : 'en'));
const targetUrl = computed(() => page.props.alternateUrls?.[target.value] ?? null);
</script>
