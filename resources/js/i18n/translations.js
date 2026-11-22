import { DEFAULT_LOCALE } from './localizePath.js';

const lazyLanguages = import.meta.glob('../../../lang/*.json');
const eagerLanguages = import.meta.glob('../../../lang/*.json', { eager: true, import: 'default' });

const fileOf = (lang) => `../../../lang/${lang}.json`;

/**
 * The browser downloads only the language of the page, and mounts the application once it
 * is there: mounting earlier would show the translation keys for a moment.
 */
export function browserI18nOptions(locale, onFirstLoad) {
    let loaded = false;

    return {
        lang: locale ?? DEFAULT_LOCALE,
        fallbackLang: DEFAULT_LOCALE,
        resolve: (lang) => lazyLanguages[fileOf(lang)]?.(),
        onLoad: () => {
            if (!loaded) {
                loaded = true;
                onFirstLoad();
            }
        },
    };
}

/**
 * Server rendering and tests read the languages synchronously, with one instance per
 * application: a shared one would leak the language of a request into the next.
 */
export function eagerI18nOptions(locale) {
    return {
        lang: locale ?? DEFAULT_LOCALE,
        fallbackLang: DEFAULT_LOCALE,
        shared: false,
        resolve: (lang) => eagerLanguages[fileOf(lang)],
    };
}
