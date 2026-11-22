// Mirror of App\Http\Character\CharacterSheetSection: the server routes these slugs, the same in
// both languages. Labels are translation keys.
export const OVERVIEW = 'apercu';

export const SECTIONS = Object.freeze([
    { value: OVERVIEW, label: 'sheet.sections.overview', subs: [] },
    {
        value: 'progression',
        label: 'sheet.sections.progress',
        subs: [
            { value: 'quetes', label: 'score.dimension.quests' },
            { value: 'hauts-faits', label: 'score.dimension.achievements' },
            { value: 'reputations', label: 'score.dimension.reputations' },
            { value: 'metiers', label: 'score.dimension.professions' },
        ],
    },
    {
        value: 'endgame',
        label: 'sheet.sections.endgame',
        subs: [
            { value: 'mythique-plus', label: 'sheet.sections.mythic_plus' },
            { value: 'raids', label: 'score.dimension.raids' },
            { value: 'pvp', label: 'sheet.sections.pvp' },
            { value: 'equipement', label: 'sheet.sections.equipment' },
        ],
    },
    {
        value: 'collections',
        label: 'sheet.sections.collections',
        subs: [
            { value: 'montures', label: 'score.dimension.mounts' },
            { value: 'mascottes', label: 'score.dimension.pets' },
            { value: 'decorations', label: 'score.dimension.decor' },
            { value: 'garde-robe', label: 'score.dimension.transmog' },
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
