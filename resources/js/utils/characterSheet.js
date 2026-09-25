// Mirror of App\Http\Character\CharacterSheetSection: the server routes these slugs.
export const OVERVIEW = 'apercu';

export const SECTIONS = Object.freeze([
    { value: OVERVIEW, label: 'Aperçu', subs: [] },
    {
        value: 'progression',
        label: 'Progression',
        subs: [
            { value: 'quetes', label: 'Quêtes' },
            { value: 'hauts-faits', label: 'Hauts-faits' },
            { value: 'reputations', label: 'Réputations' },
            { value: 'metiers', label: 'Métiers' },
        ],
    },
    {
        value: 'endgame',
        label: 'Endgame',
        subs: [
            { value: 'mythique-plus', label: 'Mythique+' },
            { value: 'raids', label: 'Raids' },
            { value: 'pvp', label: 'PvP' },
            { value: 'equipement', label: 'Équipement et talents' },
        ],
    },
    {
        value: 'collections',
        label: 'Collections',
        subs: [
            { value: 'montures', label: 'Montures' },
            { value: 'mascottes', label: 'Mascottes' },
            { value: 'decorations', label: 'Décorations' },
            { value: 'garde-robe', label: 'Garde-robe' },
        ],
    },
]);

export class UnknownSheetViewError extends Error {
    constructor(section, sub) {
        super(`Unknown character sheet view: ${section}/${sub}`);
        this.name = 'UnknownSheetViewError';
    }
}

const sectionOf = (value) => SECTIONS.find((section) => section.value === value);

export function viewOf(section, sub) {
    if (section === null || section === undefined || section === OVERVIEW) {
        return { section: OVERVIEW, sub: null };
    }

    const known = sectionOf(section);
    if (!known) {
        throw new UnknownSheetViewError(section, sub);
    }
    if (sub === null || sub === undefined) {
        return { section, sub: known.subs[0].value };
    }
    if (!known.subs.some((candidate) => candidate.value === sub)) {
        throw new UnknownSheetViewError(section, sub);
    }

    return { section, sub };
}

export function sheetUrl(realm, name, section, sub) {
    const base = `/character/${encodeURIComponent(realm)}/${encodeURIComponent(name)}`;

    if (section === OVERVIEW) {
        return base;
    }

    return `${base}/${section}/${sub}`;
}
