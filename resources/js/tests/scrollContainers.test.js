// @vitest-environment node
import { readdirSync, readFileSync } from 'node:fs';
import { relative, resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const ROOT = resolve(__dirname, '..');
const SCROLLER_CLASSES = /class="([^"]*\boverflow-x-auto\b[^"]*)"/g;

const vueFiles = (directory) => readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = resolve(directory, entry.name);
    if (entry.isDirectory()) return vueFiles(path);

    return entry.name.endsWith('.vue') ? [path] : [];
});

const scrolling = vueFiles(ROOT)
    .map((path) => [relative(ROOT, path), readFileSync(path, 'utf8')])
    .filter(([, source]) => SCROLLER_CLASSES.test(source) && !(SCROLLER_CLASSES.lastIndex = 0));

const SCROLLER_TAGS = /<div\b[^>]*\boverflow-x-auto\b[^>]*>/g;

// A visually hidden label is absolutely positioned: outside a positioned scroller, it widens the page on a phone.
describe.each(scrolling)('%s', (file, source) => {
    it('keeps what its horizontal scrollers hold inside them', () => {
        [...source.matchAll(SCROLLER_CLASSES)].forEach(([, classes]) => expect(classes.split(/\s+/)).toContain('relative'));
    });

    // A table may hold nothing focusable: a keyboard user could then never scroll it.
    it.runIf(source.includes('<table'))('lets the keyboard reach and scroll its tables, under a name', () => {
        [...source.matchAll(SCROLLER_TAGS)].forEach(([tag]) => {
            expect(tag).toContain('tabindex="0"');
            expect(tag).toContain('role="region"');
            expect(tag).toMatch(/:?aria-label(?:ledby)?="/);
        });
    });
});
