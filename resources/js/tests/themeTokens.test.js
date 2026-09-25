import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';
import { contrastRatio } from '../utils/contrast';

const STYLESHEET = resolve(__dirname, '../../css/app.css');
const TOKEN_PREFIX = '--wp-';
const TEXT_MINIMUM = 4.5;
const CONTROL_BORDER_MINIMUM = 3;

const THEMES = { light: ':root', dark: '.dark' };
const SURFACES = ['background', 'surface', 'surface-raised'];
const TEXT_TOKENS = ['text', 'text-muted', 'text-subtle', 'accent', 'info', 'success', 'warning', 'danger'];
const ALL_TOKENS = [...SURFACES, 'border', 'border-strong', ...TEXT_TOKENS, 'on-accent'];

function readThemeTokens(css, selector) {
    const blockPattern = /(?:^|\n)([^{}\n]+?)\s*\{([^{}]*)\}/g;
    const declarationPattern = /(--wp-[a-z-]+)\s*:\s*([^;]+);/g;
    const tokens = {};

    for (const [, blockSelector, body] of css.matchAll(blockPattern)) {
        if (blockSelector.trim() !== selector) {
            continue;
        }

        for (const [, name, value] of body.matchAll(declarationPattern)) {
            tokens[name.slice(TOKEN_PREFIX.length)] = value.trim();
        }
    }

    return tokens;
}

const css = readFileSync(STYLESHEET, 'utf8');

describe.each(Object.entries(THEMES))('semantic tokens of the %s theme', (theme, selector) => {
    const tokens = readThemeTokens(css, selector);

    it('declares every semantic token', () => {
        expect(Object.keys(tokens).sort()).toEqual([...ALL_TOKENS].sort());
    });

    describe.each(TEXT_TOKENS)('%s', (token) => {
        it.each(SURFACES)(`stays readable on %s (${TEXT_MINIMUM}:1)`, (surface) => {
            expect(contrastRatio(tokens[token], tokens[surface])).toBeGreaterThanOrEqual(TEXT_MINIMUM);
        });
    });

    it(`keeps text on the accent readable (${TEXT_MINIMUM}:1)`, () => {
        expect(contrastRatio(tokens['on-accent'], tokens.accent)).toBeGreaterThanOrEqual(TEXT_MINIMUM);
    });

    it.each(SURFACES)(`makes control borders visible on %s (${CONTROL_BORDER_MINIMUM}:1)`, (surface) => {
        expect(contrastRatio(tokens['border-strong'], tokens[surface])).toBeGreaterThanOrEqual(CONTROL_BORDER_MINIMUM);
    });
});

describe('brand tokens', () => {
    it('declares the Discord blue once, as a theme colour', () => {
        expect(css).toMatch(/--color-brand-discord:\s*#5865f2;/i);
    });

    // The preview of an announcement shows it as Discord draws it, in either theme of the panel.
    it.each([
        ['text', '#dbdee1'],
        ['muted', '#b5bac1'],
        ['link', '#00a8fc'],
    ])(`declares the Discord embed %s colour, readable on the embed (${TEXT_MINIMUM}:1)`, (role, colour) => {
        expect(css).toMatch(/--color-brand-discord-embed:\s*#2b2d31;/i);
        expect(css).toMatch(new RegExp(`--color-brand-discord-${role}:\\s*${colour};`, 'i'));
        expect(contrastRatio(colour, '#2b2d31')).toBeGreaterThanOrEqual(TEXT_MINIMUM);
    });

    it(`keeps white text readable on the Discord blue (${TEXT_MINIMUM}:1)`, () => {
        expect(css).toMatch(/--color-on-brand-discord:\s*#ffffff;/i);
        expect(contrastRatio('#ffffff', '#5865f2')).toBeGreaterThanOrEqual(TEXT_MINIMUM);
    });
});

