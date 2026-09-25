import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';
import { contrastRatio } from './contrast';
import {
    CLASS_IDS,
    DIMENSION_KEYS,
    FACTIONS,
    QUALITIES,
    RANKS,
    STANDINGS,
    THEME_SURFACES,
    UnknownWowColorError,
    classColor,
    colorForTheme,
    dimensionColor,
    factionColor,
    qualityColor,
    rankColor,
    readableVariants,
    rgbToHex,
    standingColor,
} from './wowColors';

const TEXT_MINIMUM = 4.5;
const MINIMUM_HUE_GAP = 30;

function hue(hex) {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
    const max = Math.max(r, g, b);
    const delta = max - Math.min(r, g, b);
    if (delta === 0) return 0;
    const sector = max === r ? ((g - b) / delta + 6) % 6 : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4;

    return sector * 60;
}

function readsOnEverySurface(hex, theme) {
    return THEME_SURFACES[theme].every((surface) => contrastRatio(hex, surface) >= TEXT_MINIMUM);
}

const allEntries = [
    ...CLASS_IDS.map((id) => [`class ${id}`, classColor(id)]),
    ...QUALITIES.map((quality) => [`quality ${quality}`, qualityColor(quality)]),
    ...FACTIONS.map((faction) => [`faction ${faction}`, factionColor(faction)]),
    ...RANKS.map((rank) => [`rank ${rank}`, rankColor(rank)]),
    ...DIMENSION_KEYS.map((key) => [`dimension ${key}`, dimensionColor(key)]),
    ...STANDINGS.map((standing) => [`standing ${standing}`, standingColor(standing)]),
];

describe('theme surfaces', () => {
    it('match the semantic surfaces declared in app.css', () => {
        const css = readFileSync(resolve(__dirname, '../../css/app.css'), 'utf8');
        const surfaces = (selector) => ['background', 'surface', 'surface-raised'].map((name) => {
            const block = css.match(new RegExp(`\\n${selector.replace('.', '\\.')}\\s*\\{([^}]*--wp-background[^}]*)\\}`))[1];

            return block.match(new RegExp(`--wp-${name}:\\s*(#[0-9a-f]+);`, 'i'))[1].toUpperCase();
        });

        expect(THEME_SURFACES.light).toEqual(surfaces(':root'));
        expect(THEME_SURFACES.dark).toEqual(surfaces('.dark'));
    });
});

describe('official values', () => {
    it('uses the current Blizzard class colours', () => {
        expect(classColor(6).base).toBe('#C41E3A');
        expect(classColor(5).base).toBe('#FFFFFF');
        expect(classColor(13).base).toBe('#33937F');
        expect(CLASS_IDS).toHaveLength(13);
    });

    it('covers the eight item qualities, heirloom and artifact included', () => {
        expect(QUALITIES).toEqual(['POOR', 'COMMON', 'UNCOMMON', 'RARE', 'EPIC', 'LEGENDARY', 'ARTIFACT', 'HEIRLOOM']);
        expect(qualityColor('EPIC').base).toBe('#A335EE');
        expect(qualityColor('HEIRLOOM').base).toBe('#00CCFF');
    });

    it('gives each faction its colour', () => {
        expect(factionColor('ALLIANCE').base).toBe('#3B82F6');
        expect(factionColor('HORDE').base).toBe('#DC2626');
    });

    it('draws the score ranks from the item qualities', () => {
        expect(rankColor('Légendaire')).toEqual(qualityColor('LEGENDARY'));
        expect(rankColor('Épique')).toEqual(qualityColor('EPIC'));
        expect(rankColor('Rare')).toEqual(qualityColor('RARE'));
        expect(rankColor('Commun')).toEqual(qualityColor('UNCOMMON'));
        expect(rankColor('Débutant')).toEqual(qualityColor('POOR'));
    });

    it('colours the eight reputation tiers, from hated to exalted, and the renown', () => {
        expect(STANDINGS).toEqual(['0', '1', '2', '3', '4', '5', '6', '7', 'renown']);
        expect(standingColor(7)).toEqual(standingColor('7'));
    });

    it('colours each of the nine score dimensions', () => {
        expect(DIMENSION_KEYS).toEqual(['quests', 'achievements', 'reputations', 'raids', 'mounts', 'transmog', 'pets', 'decor', 'professions']);
    });
});

describe.each(allEntries)('%s', (_, color) => {
    it('keeps its official value as base', () => {
        expect(color.base).toMatch(/^#[0-9A-F]{6}$/);
    });

    it('stays readable as text on every surface of the dark theme', () => {
        expect(readsOnEverySurface(color.onDark, 'dark')).toBe(true);
    });

    it('stays readable as text on every surface of the light theme', () => {
        expect(readsOnEverySurface(color.onLight, 'light')).toBe(true);
    });
});

describe('readableVariants', () => {
    it('keeps a colour that is already readable', () => {
        expect(readableVariants('#FFFFFF').onDark).toBe('#FFFFFF');
        expect(readableVariants('#000000').onLight).toBe('#000000');
    });

    it('lightens a colour too dark for the dark theme, just enough', () => {
        const { onDark } = readableVariants('#C41E3A');

        expect(readsOnEverySurface(onDark, 'dark')).toBe(true);
        expect(Math.min(...THEME_SURFACES.dark.map((s) => contrastRatio(onDark, s)))).toBeLessThan(TEXT_MINIMUM + 0.3);
    });

    it('darkens a colour too pale for the light theme, keeping its hue', () => {
        const { onLight } = readableVariants('#FFF468');

        expect(readsOnEverySurface(onLight, 'light')).toBe(true);
        expect(Math.abs(hue(onLight) - hue('#FFF468'))).toBeLessThan(3);
    });

    it('accepts short and lower-case hex notation', () => {
        expect(readableVariants('#fff').base).toBe('#FFFFFF');
    });

    it('refuses something that is not a colour', () => {
        expect(() => readableVariants('purple')).toThrow();
    });
});

describe('normalisation', () => {
    it('reads a faction whatever its case', () => {
        expect(factionColor('horde')).toEqual(factionColor('HORDE'));
        expect(factionColor('Horde')).toEqual(factionColor('HORDE'));
    });

    it('reads a quality whatever its case', () => {
        expect(qualityColor('epic')).toEqual(qualityColor('EPIC'));
    });

    it('reads a rank whatever its case', () => {
        expect(rankColor('ÉPIQUE')).toEqual(rankColor('Épique'));
    });

    it('reads a class id given as a string', () => {
        expect(classColor('6')).toEqual(classColor(6));
    });
});

describe('unknown values', () => {
    it.each([
        ['class', () => classColor(14)],
        ['class', () => classColor(null)],
        ['quality', () => qualityColor('MYTHIC')],
        ['quality', () => qualityColor(undefined)],
        ['faction', () => factionColor('NEUTRAL')],
        ['rank', () => rankColor('Divin')],
        ['rank', () => rankColor(null)],
        ['dimension', () => dimensionColor('toys')],
        ['standing', () => standingColor(8)],
    ])('refuses an unknown %s rather than falling back silently', (kind, call) => {
        expect(call).toThrow(UnknownWowColorError);
        expect(call).toThrow(kind);
    });
});

describe('dimensions', () => {
    it('are told apart by hue, with no two closer than the minimum gap', () => {
        const hues = DIMENSION_KEYS.map((key) => hue(dimensionColor(key).base)).sort((a, b) => a - b);
        const gaps = hues.map((h, i) => (i === 0 ? h + 360 - hues.at(-1) : h - hues[i - 1]));

        expect(Math.min(...gaps)).toBeGreaterThanOrEqual(MINIMUM_HUE_GAP);
    });
});

describe('colorForTheme', () => {
    it('picks the text variant of the effective theme', () => {
        const color = classColor(5);

        expect(colorForTheme(color, 'dark')).toBe(color.onDark);
        expect(colorForTheme(color, 'light')).toBe(color.onLight);
    });

    it('refuses a theme it does not know', () => {
        expect(() => colorForTheme(classColor(5), 'system')).toThrow('"system"');
    });
});

describe('rgbToHex', () => {
    it('turns the colour objects of the API into hex', () => {
        expect(rgbToHex({ r: 255, g: 128, b: 0, a: 1 })).toBe('#FF8000');
        expect(rgbToHex({ r: 0, g: 0, b: 0 })).toBe('#000000');
    });

    it.each([[{ r: 256, g: 0, b: 0 }], [{ r: 1.5, g: 0, b: 0 }], [{ r: 0, g: 0 }], [null]])('refuses %j', (value) => {
        expect(() => rgbToHex(value)).toThrow(UnknownWowColorError);
    });
});
