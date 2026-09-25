// @vitest-environment node
import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const RAW_PALETTE = /\b(?:bg|text|border|ring|outline|fill|stroke|shadow|from|via|to|decoration|divide|placeholder)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)\b/;
const ARBITRARY_SIZE = /-\[[\d.]+(?:px|rem|em|%|vh|vw)\]/;
const ARBITRARY_COLOUR = /-\[#[0-9a-f]{3,8}\]/i;

const primitives = readdirSync(__dirname).filter((file) => file.endsWith('.vue'));

describe.each(primitives)('%s', (file) => {
    const source = readFileSync(resolve(__dirname, file), 'utf8');

    it('uses no raw palette class, only semantic tokens', () => {
        expect(source).not.toMatch(RAW_PALETTE);
    });

    it('uses no arbitrary size nor colour', () => {
        expect(source).not.toMatch(ARBITRARY_SIZE);
        expect(source).not.toMatch(ARBITRARY_COLOUR);
    });
});
