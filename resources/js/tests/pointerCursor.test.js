// @vitest-environment node
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const STYLESHEET = readFileSync(resolve(__dirname, '../../css/app.css'), 'utf8');

// Tailwind 4 leaves buttons with the default cursor: tabs, tiles and toggles would not look clickable.
describe('app.css', () => {
    it('shows the pointer on every enabled button', () => {
        expect(STYLESHEET).toMatch(/button:not\(:disabled\),\s*\[role="button"\]:not\(:disabled\)\s*\{\s*cursor:\s*pointer;\s*\}/);
    });
});
