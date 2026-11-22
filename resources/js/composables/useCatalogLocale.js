import { computed } from 'vue';
import { useLocalizedUrl } from './useLocalizedUrl.js';
import { translateCategory, translateSource } from '../utils/collections.js';

const WOWHEAD_BY_LOCALE = Object.freeze({
    fr: 'https://www.wowhead.com/fr',
    en: 'https://www.wowhead.com',
});

/**
 * What a catalogue page shows in the language of the page: the name of an entry, the label of
 * a curated category or source, and its Wowhead link. The curated taxonomy is written in
 * English, so it only needs translating for the French pages.
 */
export function useCatalogLocale() {
    const { locale } = useLocalizedUrl();
    const isEnglish = computed(() => locale.value === 'en');

    return {
        locale,
        nameOf: (entry) => (isEnglish.value ? entry.name_en ?? entry.name_fr : entry.name_fr),
        categoryLabel: (kind, name) => (isEnglish.value ? name : translateCategory(kind, name)),
        sourceLabel: (kind, name) => (isEnglish.value ? name : translateSource(kind, name)),
        wowheadLink: (path) => `${WOWHEAD_BY_LOCALE[locale.value] ?? WOWHEAD_BY_LOCALE.fr}/${path}`,
    };
}
