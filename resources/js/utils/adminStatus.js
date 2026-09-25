export class UnknownAdminStatusError extends Error {
    constructor(kind, value) {
        super(`Unknown ${kind} status: ${JSON.stringify(value)}`);
        this.name = 'UnknownAdminStatusError';
    }
}

const HEALTH = Object.freeze({
    ok: { label: 'OK', tone: 'success' },
    warning: { label: 'Attention', tone: 'warning' },
    critical: { label: 'Anomalie', tone: 'danger' },
    unavailable: { label: 'Injoignable', tone: 'danger' },
});

// A cancelled or abandoned import must not read as a complete one: a partial catalogue
// would otherwise pass for an up-to-date one.
const IMPORT = Object.freeze({
    completed: { label: 'Terminé', tone: 'success' },
    failed: { label: 'Terminé avec échecs', tone: 'danger' },
    cancelled: { label: 'Annulé', tone: 'warning' },
    abandoned: { label: 'Abandonné', tone: 'warning' },
    running: { label: 'En cours', tone: 'info' },
    paused: { label: 'En pause', tone: 'info' },
    pending: { label: 'En attente', tone: 'info' },
    skipped: { label: 'Déjà à jour', tone: 'neutral' },
});

export function healthStatus(status) {
    if (!Object.hasOwn(HEALTH, status)) {
        throw new UnknownAdminStatusError('health', status);
    }

    return { ...HEALTH[status] };
}

export function importStatus(status) {
    return Object.hasOwn(IMPORT, status) ? { ...IMPORT[status] } : { label: String(status), tone: 'neutral' };
}
