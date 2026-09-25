// @vitest-environment node
import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';
import { LEGACY_PALETTE } from './helpers';

const ROOT = resolve(__dirname, '..');

const vueFiles = (directory, keep = () => true) => readdirSync(resolve(ROOT, directory))
    .filter((file) => file.endsWith('.vue') && keep(file))
    .map((file) => `${directory}/${file}`);

const ADMINISTRATION = [
    'layouts/AdminLayout.vue',
    ...vueFiles('pages', (file) => file.startsWith('Admin')),
    ...vueFiles('components/admin'),
];

describe.each(ADMINISTRATION)('%s', (file) => {
    it('draws only with the tokens of the design system', () => {
        expect(readFileSync(resolve(ROOT, file), 'utf8')).not.toMatch(LEGACY_PALETTE);
    });
});
