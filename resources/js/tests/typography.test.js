import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const css = readFileSync(resolve(__dirname, '../../css/app.css'), 'utf8');

function themeValue(name) {
    return css.match(new RegExp(`${name}\\s*:\\s*([^;]+);`))?.[1].trim() ?? null;
}

function fontFace(family) {
    const faces = css.match(/@font-face\s*\{[^}]*\}/g) ?? [];

    return faces.find((face) => face.includes(`font-family: '${family}'`)) ?? null;
}

describe('typography', () => {
    it('serves Inter and the three Cinzel weights from the site itself', () => {
        const imports = [...css.matchAll(/@import\s+"([^"]+)";/g)].map(([, path]) => path);

        expect(imports).toEqual(expect.arrayContaining([
            '@fontsource-variable/inter/wght.css',
            '@fontsource/cinzel/400.css',
            '@fontsource/cinzel/600.css',
            '@fontsource/cinzel/700.css',
        ]));
        expect(css).not.toMatch(/fonts\.googleapis|Outfit/);
    });

    it('sets Inter as the body font, with a metric-matched fallback while it loads', () => {
        const stack = themeValue('--font-sans');

        expect(stack).toMatch(/^'Inter Variable', 'Inter Fallback',/);
        expect(stack).toContain('sans-serif');
    });

    it('declares Cinzel as the display font, for titles only', () => {
        expect(themeValue('--font-display')).toMatch(/^'Cinzel', 'Cinzel Fallback',.*serif$/);
    });

    // Cinzel titles every page: without a matched fallback, its arrival shifts the layout (CLS 0.19 on the PvP page).
    it('shapes the fallback of Cinzel on a local serif so the swap does not shift the titles', () => {
        const fallback = fontFace('Cinzel Fallback');

        expect(fallback).toContain("src: local('Times New Roman'), local('Liberation Serif')");
        for (const descriptor of ['size-adjust', 'ascent-override', 'descent-override', 'line-gap-override']) {
            expect(fallback).toMatch(new RegExp(`${descriptor}:\\s*[\\d.]+%`));
        }
    });

    it('shapes the fallback on a local font so the swap to Inter does not shift the layout', () => {
        const fallback = fontFace('Inter Fallback');

        expect(fallback).toContain("src: local('Arial')");
        for (const descriptor of ['size-adjust', 'ascent-override', 'descent-override', 'line-gap-override']) {
            expect(fallback).toMatch(new RegExp(`${descriptor}:\\s*[\\d.]+%`));
        }
    });
});
