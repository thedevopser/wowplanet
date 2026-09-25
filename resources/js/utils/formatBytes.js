const UNITS = ['o', 'ko', 'Mo', 'Go'];
const STEP = 1024;
const UNKNOWN = '—';

/**
 * Une taille en octets, lisible.
 *
 * La décimale n'apparaît qu'à partir du mégaoctet : sous cette barre elle n'apporte rien,
 * au-dessus elle sépare les 43,5 Mo d'un fichier de référence des 100 Mo du magasin entier.
 */
export function formatBytes(bytes) {
    if (typeof bytes !== 'number' || !Number.isFinite(bytes) || bytes < 0) {
        return UNKNOWN;
    }

    let value = bytes;
    let unit = 0;

    while (value >= STEP && unit < UNITS.length - 1) {
        value /= STEP;
        unit += 1;
    }

    const decimals = unit >= 2 && value < 100 ? 1 : 0;

    return `${value.toLocaleString('fr-FR', { maximumFractionDigits: decimals })} ${UNITS[unit]}`;
}
