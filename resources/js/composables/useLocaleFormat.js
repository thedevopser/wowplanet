import { useActiveLocale } from './useActiveLocale.js';
import { formatScore } from '../utils/formatScore.js';

/**
 * Numbers, scores, dates and alphabetical order in the language of the page.
 */
export function useLocaleFormat() {
    const locale = useActiveLocale();

    return {
        locale,
        formatNumber: (value) => Number(value ?? 0).toLocaleString(locale.value),
        formatScore: (score) => formatScore(score, locale.value),
        formatDate: (timestamp, options) => new Date(timestamp).toLocaleDateString(locale.value, options),
        compareNames: (first, second) => first.localeCompare(second, locale.value),
    };
}
