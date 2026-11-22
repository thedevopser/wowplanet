import { inject } from 'vue';

/**
 * The translation function of the application, for the texts a script builds, such as table
 * columns. Templates use `$t` directly.
 */
export function useTranslate() {
    const i18n = inject('i18n');

    return (key, replacements = {}) => i18n.trans(key, replacements);
}

/**
 * The translation of a text that depends on a count, such as « 1 dungeon » and « 3 dungeons ».
 */
export function useTranslateChoice() {
    const i18n = inject('i18n');

    return (key, count, replacements = {}) => i18n.transChoice(key, count, replacements);
}
