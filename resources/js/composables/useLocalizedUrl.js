import { localizePath } from '../i18n/localizePath.js';
import { useActiveLocale } from './useActiveLocale.js';

/**
 * Internal links are written with their French path, the canonical one, and follow the
 * language the page is rendered in.
 */
export function useLocalizedUrl() {
    const locale = useActiveLocale();

    return {
        locale,
        localizedUrl: (path) => localizePath(path, locale.value),
    };
}
