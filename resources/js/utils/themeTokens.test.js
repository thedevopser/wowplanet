import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';
import { DARK_THEME } from './themeTokens';

const stylesheet = readFileSync(resolve(__dirname, '../../css/app.css'), 'utf8');
const darkBlock = stylesheet.slice(stylesheet.indexOf('.dark {'), stylesheet.indexOf('}', stylesheet.indexOf('.dark {')));

describe('DARK_THEME', () => {
    it.each(Object.entries(DARK_THEME))('%s holds the value of the stylesheet', (name, value) => {
        const declared = darkBlock.match(new RegExp(`--wp-${name}:\\s*([^;]+);`))?.[1].trim();

        expect(value.toLowerCase()).toBe(declared?.toLowerCase());
    });
});
