import { inject } from 'vue';

/**
 * The translation function of the application, for the texts a script builds, such as table
 * columns. Templates use `$t` directly.
 */
export function useTranslate() {
    const i18n = inject('i18n');

    return (key, replacements = {}) => i18n.trans(key, replacements);
}
