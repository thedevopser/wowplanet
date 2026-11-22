import axios from 'axios';

export const LOCALE_HEADER = 'X-App-Locale';

export function applyPageLocale(locale) {
    axios.defaults.headers.common[LOCALE_HEADER] = locale;
    if (window.whTooltips) {
        window.whTooltips.locale = locale;
    }
}
