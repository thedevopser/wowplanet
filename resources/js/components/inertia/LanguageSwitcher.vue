<template>
    <DropdownMenu
        v-if="alternateUrls"
        :items="[]"
        :label="$t('locale.menu')"
        :choices="choices"
        :choice="currentLocale"
        @update:choice="switchTo"
    >
        <template #trigger>
            <Button variant="ghost" :aria-label="$t('locale.trigger', { name: current.label })">
                <img :src="current.flag" alt="" width="20" height="14" class="h-3.5 w-5 rounded-ui-sm object-cover">
                <span>{{ current.label }}</span>
                <Icon :icon="ChevronDown" size="sm" />
            </Button>
        </template>
        <template #choice="{ option }">
            <img :src="option.flag" alt="" width="20" height="14" class="h-3.5 w-5 rounded-ui-sm object-cover">
            <span :lang="option.value">{{ option.label }}</span>
        </template>
    </DropdownMenu>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ChevronDown } from 'lucide-vue-next';
import Button from '../ui/Button.vue';
import DropdownMenu from '../ui/DropdownMenu.vue';
import Icon from '../ui/Icon.vue';
import { rememberLocale } from '../../i18n/localeChoice';
import { DEFAULT_LOCALE } from '../../i18n/localizePath';

// Each language is named in itself: a visitor who cannot read the page still finds theirs.
const LANGUAGES = Object.freeze([
    { value: 'fr', label: 'Français', flag: '/images/flags/fr.svg' },
    { value: 'en', label: 'English', flag: '/images/flags/gb.svg' },
]);

const page = usePage();
const currentLocale = computed(() => page.props.locale ?? DEFAULT_LOCALE);
const alternateUrls = computed(() => page.props.alternateUrls ?? null);
const current = computed(() => LANGUAGES.find((language) => language.value === currentLocale.value) ?? LANGUAGES[0]);
const choices = computed(() => ({ label: trans('locale.menu'), options: LANGUAGES }));

function switchTo(locale) {
    if (locale === currentLocale.value) {
        return;
    }
    rememberLocale(locale);
    router.visit(alternateUrls.value[locale]);
}
</script>
