const UNKNOWN = '—';

/**
 * Une durée en secondes, lisible : la seconde tant qu'elle compte, puis la minute, puis
 * l'heure, jamais plus de deux unités.
 */
export function formatDuration(seconds) {
    if (typeof seconds !== 'number' || !Number.isFinite(seconds) || seconds < 0) {
        return UNKNOWN;
    }

    if (seconds < 60) return `${seconds} s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min ${String(seconds % 60).padStart(2, '0')} s`;

    return `${Math.floor(seconds / 3600)} h ${String(Math.floor((seconds % 3600) / 60)).padStart(2, '0')} min`;
}
