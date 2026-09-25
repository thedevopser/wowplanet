import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const stylesheet = readFileSync(resolve(__dirname, '../../css/app.css'), 'utf8');
const maintenance = readFileSync(resolve(__dirname, '../../views/errors/503.blade.php'), 'utf8');

const DECLARATION = /(--wp-[a-z-]+)\s*:\s*([^;]+);/g;

function tokensIn(block) {
    return Object.fromEntries([...block.matchAll(DECLARATION)].map(([, name, value]) => [name, value.trim().toLowerCase()]));
}

function blockAfter(source, opening) {
    const start = source.indexOf(opening);
    if (start === -1) {
        return '';
    }

    return source.slice(start + opening.length, source.indexOf('}', start));
}

const appLight = tokensIn(blockAfter(stylesheet, ':root {'));
const appDark = tokensIn(blockAfter(stylesheet, '.dark {'));
const pageLight = tokensIn(blockAfter(maintenance, ':root {'));
const pageDark = tokensIn(blockAfter(maintenance, '@media (prefers-color-scheme: dark) {'));

describe('maintenance page', () => {
    it('declares tokens for both themes', () => {
        expect(Object.keys(pageLight).length).toBeGreaterThan(0);
        expect(Object.keys(pageDark)).toEqual(Object.keys(pageLight));
    });

    it.each([['light', pageLight, appLight], ['dark', pageDark, appDark]])('takes the values of the %s theme of the site', (theme, page, app) => {
        Object.entries(page).forEach(([name, value]) => expect([name, value]).toEqual([name, app[name]]));
    });

    it('writes no colour outside its tokens', () => {
        const rules = maintenance.replace(/--wp-[a-z-]+\s*:\s*[^;]+;/g, '');

        expect(rules).not.toMatch(/#[0-9a-f]{3,8}\b|rgba?\(/i);
    });

    it('stops its animation for readers who ask for less motion', () => {
        expect(maintenance).toContain('prefers-reduced-motion: reduce');
    });
});
