import { computed, inject } from 'vue';
import { DEFAULT_LOCALE } from '../i18n/localizePath.js';

/**
 * The language the texts are rendered in, for what translation keys cannot carry, such as
 * number and date formatting.
 */
export function useActiveLocale() {
    const i18n = inject('i18n', null);

    return computed(() => i18n?.currentLanguage?.value ?? DEFAULT_LOCALE);
}
