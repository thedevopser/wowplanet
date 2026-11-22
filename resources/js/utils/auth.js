// A full page load, never an Inertia visit: the route answers with a redirect to Battle.net.
export const LOGIN_URL = '/auth/blizzard/redirect';

const DEFAULT_LOCALE = 'fr';

// The server brings the player back to the language the login started from.
export function loginUrl(locale) {
    return locale === DEFAULT_LOCALE ? LOGIN_URL : `${LOGIN_URL}?${new URLSearchParams({ lang: locale })}`;
}
