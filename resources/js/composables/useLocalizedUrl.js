import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { DEFAULT_LOCALE, localizePath } from '../i18n/localizePath.js';

/**
 * Internal links are written with their French path, the canonical one, and follow the
 * locale of the current page.
 */
export function useLocalizedUrl() {
    const page = usePage();
    const locale = computed(() => page.props.locale ?? DEFAULT_LOCALE);

    return {
        locale,
        localizedUrl: (path) => localizePath(path, locale.value),
    };
}
