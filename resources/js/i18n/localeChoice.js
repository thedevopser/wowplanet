import localizedRoutes from './localizedRoutes.json';

const COOKIE = 'wowplanet-locale';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;
const LOCALES = Object.keys(Object.values(localizedRoutes)[0]);

/**
 * The server reads this cookie in clear to stop suggesting the other language.
 */
export function rememberLocale(locale) {
    if (!LOCALES.includes(locale)) {
        throw new Error(`Unknown locale "${locale}"`);
    }
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${COOKIE}=${locale}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax${secure}`;
}
