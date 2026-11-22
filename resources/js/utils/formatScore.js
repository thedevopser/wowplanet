// The same score reads the same everywhere: one decimal at most, in the language of the page.
export function formatScore(score, locale = 'fr') {
    return Number(score).toLocaleString(locale, { maximumFractionDigits: 1 });
}
