const CONSOLE_TRIGGER = 'console';
const UNKNOWN = '—';

export const MODES = {
    incremental: 'Incrémental',
    forced: 'Forcé',
};

export function triggerLabel(trigger) {
    return trigger === CONSOLE_TRIGGER ? 'Console' : trigger;
}

export function formatHistoryDate(isoDate) {
    return new Date(isoDate).toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

/**
 * Un écart de volume porte toujours son signe : une baisse doit se lire comme telle, et
 * le vrai signe moins la distingue d'un tiret de mise en page.
 */
export function formatSigned(delta) {
    if (typeof delta !== 'number') {
        return UNKNOWN;
    }

    const magnitude = Math.abs(delta).toLocaleString('fr-FR');

    if (delta > 0) return `+${magnitude}`;
    if (delta < 0) return `−${magnitude}`;

    return magnitude;
}
