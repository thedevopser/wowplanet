// The same score reads the same everywhere: one decimal at most, with a French comma.
export function formatScore(score) {
    return Number(score).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
}
