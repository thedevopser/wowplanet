import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderScoreCard } from './scoreCardRenderer';
import { DARK_THEME } from './themeTokens';
import { classColor, dimensionColor, rankColor } from './wowColors';

// Every colour the card paints with, recorded with the text or shape it painted.
const painted = [];
let currentFill = '';
let currentFontSize = 10;
let currentAlign = 'start';
// Text as wide as a narrow sans-serif would draw it, so that layouts can be checked.
const widthOf = (text) => String(text).length * currentFontSize * 0.6;

const mockCtx = {
    fillRect: vi.fn(() => painted.push(['rect', currentFill])),
    fillText: vi.fn((text, x) => painted.push([text, currentFill, x, currentAlign, widthOf(text)])),
    fill: vi.fn(() => painted.push(['shape', currentFill])),
    stroke: vi.fn(),
    beginPath: vi.fn(),
    moveTo: vi.fn(),
    arcTo: vi.fn(),
    closePath: vi.fn(),
    roundRect: vi.fn(),
    measureText: vi.fn((text) => ({ width: widthOf(text) })),
    scale: vi.fn(),
    createLinearGradient: vi.fn(() => ({ addColorStop: vi.fn() })),
    set fillStyle(value) {
        currentFill = value;
    },
    set globalAlpha(_) {},
    set strokeStyle(_) {},
    set lineWidth(_) {},
    set font(value) {
        currentFontSize = Number(value.match(/(\d+)px/)[1]);
    },
    set textBaseline(_) {},
    set textAlign(value) {
        currentAlign = value;
    },
};

beforeEach(() => {
    vi.clearAllMocks();
    painted.length = 0;
    currentFill = '';
    currentFontSize = 10;
    currentAlign = 'start';
    vi.stubGlobal('document', {
        createElement: vi.fn((tag) => {
            if (tag === 'canvas') {
                return { width: 0, height: 0, getContext: () => mockCtx };
            }
            return {};
        }),
    });
});

const dimension = (key, label, completed, total, score, applicable = true) =>
    ({ key, label, completed, total, score, applicable, weight: 0.1 });

const baseDimensions = [
    dimension('quests', 'Quêtes', 50, 100, 50),
    dimension('achievements', 'Hauts-faits', 30, 100, 30),
    dimension('reputations', 'Réputations', 10, 20, 50),
    dimension('mounts', 'Montures', 2, 3, 66.7),
    dimension('pets', 'Mascottes', 1, 2, 50),
    dimension('decor', 'Décorations', 1, 1, 100),
    dimension('professions', 'Métiers', 5, 10, 50),
];

describe('renderScoreCard', () => {
    it('draws a 700 by 430 card at twice its size, so that the shared image stays sharp', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            characterRealm: 'Hyjal',
            characterClass: 'Guerrier',
            characterRace: 'Humain',
            characterLevel: 80,
            classId: 1,
            globalScore: 52,
            rank: 'Gold',
            dimensions: baseDimensions,
        });

        expect(canvas.width).toBe(1400);
        expect(canvas.height).toBe(860);
        expect(mockCtx.scale).toHaveBeenCalledWith(2, 2);
    });

    it('handles personal variant', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'MyHero',
            characterRealm: 'Archimonde',
            characterClass: 'Mage',
            characterRace: 'Elfe de sang',
            characterLevel: 80,
            classId: 8,
            globalScore: 75,
            rank: 'Platine',
            dimensions: baseDimensions,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(1400);
        // Verify fillText was called (for character name, score, etc.)
        expect(mockCtx.fillText).toHaveBeenCalled();
    });

    it('handles account variant', () => {
        const canvas = renderScoreCard({
            variant: 'account',
            characterCount: 5,
            globalScore: 60,
            rank: 'Or',
            dimensions: baseDimensions,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(1400);
        expect(mockCtx.fillText).toHaveBeenCalled();
    });

    it('grows by one row per extra dimension', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            globalScore: 52,
            rank: 'Or',
            dimensions: [
                ...baseDimensions,
                dimension('transmog', 'Garde-robe', 300, 1000, 30),
                dimension('raids', 'Raids', 4, 8, 50),
            ],
        });

        expect(canvas.height).toBe(980);
    });

    it('ignores non-applicable dimensions', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            globalScore: 52,
            rank: 'Or',
            dimensions: [
                ...baseDimensions,
                dimension('raids', 'Raids', 0, 0, 0, false),
            ],
        });

        expect(canvas.height).toBe(860);
    });

    it('handles missing dimensions gracefully', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            characterRealm: 'Hyjal',
            characterClass: 'Guerrier',
            characterRace: 'Humain',
            characterLevel: 80,
            classId: 1,
            globalScore: 0,
            rank: 'Bronze',
            dimensions: undefined,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(1400);
        expect(canvas.height).toBe(440);
    });

    it('handles missing character info gracefully', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            globalScore: 0,
            rank: '',
            dimensions: [],
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(1400);
    });

    it('calls createLinearGradient for background', () => {
        renderScoreCard({
            variant: 'personal',
            characterName: 'Test',
            globalScore: 50,
            rank: 'Silver',
            dimensions: baseDimensions,
        });

        expect(mockCtx.createLinearGradient).toHaveBeenCalledWith(0, 0, 700, 430);
    });

    const personalCard = {
        variant: 'personal',
        characterName: 'Arthas',
        characterRealm: 'Hyjal',
        characterClass: 'Chevalier de la mort',
        characterRace: 'Humain',
        characterLevel: 80,
        classId: 6,
        globalScore: 62,
        rank: 'Rare',
        dimensions: baseDimensions,
    };

    const colourOf = (text) => painted.find(([painting]) => painting === text)?.[1];

    it('paints only with the dark theme tokens and the game colours', () => {
        renderScoreCard(personalCard);

        const allowed = new Set([
            ...Object.values(DARK_THEME),
            classColor(6).onDark,
            rankColor('Rare').onDark,
            ...baseDimensions.map((row) => dimensionColor(row.key).onDark),
        ].map((colour) => colour.toLowerCase()));
        const used = painted.map(([, colour]) => colour).filter((colour) => typeof colour === 'string');

        used.forEach((colour) => expect(allowed).toContain(colour.toLowerCase()));
    });

    it('writes the character name in the readable colour of its class', () => {
        renderScoreCard(personalCard);

        expect(colourOf('Arthas').toLowerCase()).toBe(classColor(6).onDark.toLowerCase());
    });

    it('colours the score and its rank with the colour of the rank', () => {
        renderScoreCard(personalCard);

        expect(colourOf('62').toLowerCase()).toBe(rankColor('Rare').onDark.toLowerCase());
        expect(colourOf('RARE').toLowerCase()).toBe(rankColor('Rare').onDark.toLowerCase());
    });

    it('sets « / 100 » after the score instead of over it', () => {
        renderScoreCard({ ...personalCard, globalScore: 25.8 });

        const edges = (text) => {
            const [, , x, align, width] = painted.find(([painting]) => painting === text);
            const left = align === 'center' ? x - width / 2 : x;
            return { left, right: left + width };
        };

        expect(edges('/ 100').left).toBeGreaterThan(edges('25.8').right);
    });

    it('centres the score and « / 100 » together on the card', () => {
        renderScoreCard({ ...personalCard, globalScore: 25.8 });

        const [, , scoreX, scoreAlign] = painted.find(([painting]) => painting === '25.8');
        const [, , outOfX, , outOfWidth] = painted.find(([painting]) => painting === '/ 100');

        expect(scoreAlign).toBe('left');
        expect((scoreX + outOfX + outOfWidth) / 2).toBeCloseTo(350);
    });

    it('paints a rank or a class Blizzard adds later in the plain text colour', () => {
        renderScoreCard({ ...personalCard, classId: 99, rank: 'Mythique' });

        expect(colourOf('Arthas')).toBe(DARK_THEME.text);
        expect(colourOf('MYTHIQUE')).toBe(DARK_THEME.text);
    });
});

