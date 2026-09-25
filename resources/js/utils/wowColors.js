import { contrastRatio } from './contrast';

/*
 * Game colours, defined once. Each entry carries `base`, the official value, for anything
 * that is not text (edge, bar, dot), and `onDark` / `onLight`, the same hue adjusted to reach
 * 4.5:1 as text on the three surfaces of each theme.
 */

const TEXT_MINIMUM = 4.5;
const LIGHTNESS_STEP = 0.005;
const CHANNEL_MAX = 255;
const HEX_PATTERN = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
const DARK = 'dark';
const LIGHT = 'light';

export const THEME_SURFACES = Object.freeze({
    [DARK]: Object.freeze(['#0B0F1A', '#121826', '#1A2233']),
    [LIGHT]: Object.freeze(['#F7F4EC', '#FFFFFF', '#F1ECE2']),
});

export class UnknownWowColorError extends Error {
    constructor(kind, value) {
        super(`Unknown ${kind}: ${JSON.stringify(value)}`);
        this.name = 'UnknownWowColorError';
    }
}

const CLASS_BASES = {
    1: '#C69B6D', // Warrior
    2: '#F48CBA', // Paladin
    3: '#AAD372', // Hunter
    4: '#FFF468', // Rogue
    5: '#FFFFFF', // Priest
    6: '#C41E3A', // Death Knight
    7: '#0070DD', // Shaman
    8: '#3FC7EB', // Mage
    9: '#8788EE', // Warlock
    10: '#00FF98', // Monk
    11: '#FF7C0A', // Druid
    12: '#A330C9', // Demon Hunter
    13: '#33937F', // Evoker
};

const QUALITY_BASES = {
    POOR: '#9D9D9D',
    COMMON: '#FFFFFF',
    UNCOMMON: '#1EFF00',
    RARE: '#0070DD',
    EPIC: '#A335EE',
    LEGENDARY: '#FF8000',
    ARTIFACT: '#E6CC80',
    HEIRLOOM: '#00CCFF',
};

const FACTION_BASES = {
    ALLIANCE: '#3B82F6',
    HORDE: '#DC2626',
};

// Rank labels are the ones App\Domain\Services\ScoreCalculator produces.
const RANK_QUALITIES = {
    'légendaire': 'LEGENDARY',
    'épique': 'EPIC',
    'rare': 'RARE',
    'commun': 'UNCOMMON',
    'débutant': 'POOR',
};

// Reputation tiers from Hated (0) to Exalted (7), then the renown of the newer factions.
const STANDING_BASES = {
    0: '#DC2626',
    1: '#F87171',
    2: '#FB923C',
    3: '#FACC15',
    4: '#4ADE80',
    5: '#2DD4BF',
    6: '#C084FC',
    7: '#FCD34D',
    renown: '#38BDF8',
};

// Hues 40° apart, so that no two dimensions of a chart can be mistaken for each other.
const DIMENSION_BASES = {
    quests: '#3C3CDD',
    achievements: '#DDA73C',
    reputations: '#A73CDD',
    raids: '#DD3C3C',
    mounts: '#DD3CA7',
    transmog: '#3CA7DD',
    pets: '#A7DD3C',
    decor: '#3CDDA7',
    professions: '#3CDD3C',
};

function normalizeHex(hex) {
    if (typeof hex !== 'string' || !HEX_PATTERN.test(hex)) {
        throw new UnknownWowColorError('colour', hex);
    }

    const digits = hex.slice(1).toUpperCase();

    return `#${digits.length === 3 ? [...digits].map((digit) => digit + digit).join('') : digits}`;
}

function toHsl(hex) {
    const [r, g, b] = [1, 3, 5].map((offset) => parseInt(hex.slice(offset, offset + 2), 16) / CHANNEL_MAX);
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const lightness = (max + min) / 2;
    const delta = max - min;

    if (delta === 0) {
        return [0, 0, lightness];
    }

    const saturation = lightness > 0.5 ? delta / (2 - max - min) : delta / (max + min);
    const sector = max === r ? (g - b) / delta + (g < b ? 6 : 0) : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4;

    return [sector / 6, saturation, lightness];
}

function toHex(hue, saturation, lightness) {
    const chroma = saturation * Math.min(lightness, 1 - lightness);
    const channel = (offset) => {
        const k = (offset + hue * 12) % 12;
        const value = lightness - chroma * Math.max(-1, Math.min(k - 3, 9 - k, 1));

        return Math.round(value * CHANNEL_MAX).toString(16).padStart(2, '0');
    };

    return `#${channel(0)}${channel(8)}${channel(4)}`.toUpperCase();
}

function readsOn(hex, theme) {
    return THEME_SURFACES[theme].every((surface) => contrastRatio(hex, surface) >= TEXT_MINIMUM);
}

// Terminates: at full lightness the candidate is white, at none it is black, and both
// read on their theme's surfaces (guarded by the tests of THEME_SURFACES).
function readableOn(hex, theme) {
    const [hue, saturation, start] = toHsl(hex);
    const direction = theme === DARK ? 1 : -1;
    let lightness = start;
    let candidate = hex;

    while (!readsOn(candidate, theme)) {
        lightness = Math.min(1, Math.max(0, lightness + direction * LIGHTNESS_STEP));
        candidate = toHex(hue, saturation, lightness);
    }

    return candidate;
}

/**
 * Readable variants of any colour, such as the Mythic+ rating colour sent by the API.
 */
export function readableVariants(hex) {
    const base = normalizeHex(hex);

    return Object.freeze({ base, onDark: readableOn(base, DARK), onLight: readableOn(base, LIGHT) });
}

function buildTable(bases) {
    return Object.freeze(Object.fromEntries(
        Object.entries(bases).map(([key, base]) => [key, readableVariants(base)]),
    ));
}

const CLASSES = buildTable(CLASS_BASES);
const QUALITY_COLORS = buildTable(QUALITY_BASES);
const FACTION_COLORS = buildTable(FACTION_BASES);
const DIMENSIONS = buildTable(DIMENSION_BASES);
const STANDING_COLORS = buildTable(STANDING_BASES);

export const CLASS_IDS = Object.freeze(Object.keys(CLASS_BASES).map(Number));
export const QUALITIES = Object.freeze(Object.keys(QUALITY_BASES));
export const FACTIONS = Object.freeze(Object.keys(FACTION_BASES));
export const RANKS = Object.freeze(['Légendaire', 'Épique', 'Rare', 'Commun', 'Débutant']);
export const DIMENSION_KEYS = Object.freeze(Object.keys(DIMENSION_BASES));
export const STANDINGS = Object.freeze(Object.keys(STANDING_BASES));

function lookup(table, key, kind, original) {
    if (!Object.hasOwn(table, key)) {
        throw new UnknownWowColorError(kind, original);
    }

    return table[key];
}

const upper = (value) => (typeof value === 'string' ? value.toUpperCase() : value);
const lower = (value) => (typeof value === 'string' ? value.toLowerCase() : value);

export function classColor(classId) {
    return lookup(CLASSES, String(classId), 'class', classId);
}

export function qualityColor(quality) {
    return lookup(QUALITY_COLORS, upper(quality), 'quality', quality);
}

export function factionColor(faction) {
    return lookup(FACTION_COLORS, upper(faction), 'faction', faction);
}

export function rankColor(rank) {
    return qualityColor(lookup(RANK_QUALITIES, lower(rank), 'rank', rank));
}

export function dimensionColor(key) {
    return lookup(DIMENSIONS, key, 'dimension', key);
}

export function standingColor(standing) {
    return lookup(STANDING_COLORS, String(standing), 'standing', standing);
}

export function colorForTheme(color, theme) {
    if (theme === DARK) {
        return color.onDark;
    }
    if (theme === LIGHT) {
        return color.onLight;
    }

    throw new UnknownWowColorError('theme', theme);
}

export function rgbToHex(color) {
    const channels = [color?.r, color?.g, color?.b];

    if (!channels.every((value) => Number.isInteger(value) && value >= 0 && value <= CHANNEL_MAX)) {
        throw new UnknownWowColorError('rgb colour', color);
    }

    return `#${channels.map((value) => value.toString(16).padStart(2, '0')).join('')}`.toUpperCase();
}
