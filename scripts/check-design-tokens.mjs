// Fails when a component paints outside the design system: raw Tailwind palette class,
// arbitrary size or arbitrary colour. Run by `make tokens-check`, `make quality` and the CI.
import { readdirSync, readFileSync } from 'node:fs';
import { relative, resolve } from 'node:path';
import { findTokenViolations } from '../resources/js/utils/designTokenGuard.js';

const ROOT = resolve(import.meta.dirname, '..');
const SOURCES = resolve(ROOT, 'resources/js');

// Each exception names its file, its token and why it cannot use a token instead.
const ALLOWED = [];

const isSource = (name) => /\.(vue|js)$/.test(name) && !/\.(test|spec)\.js$/.test(name);

function sourcesIn(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = resolve(directory, entry.name);
        if (entry.isDirectory()) {
            return entry.name === 'tests' ? [] : sourcesIn(path);
        }

        return isSource(entry.name) ? [path] : [];
    });
}

const violations = sourcesIn(SOURCES).flatMap((path) => findTokenViolations(readFileSync(path, 'utf8'), relative(ROOT, path), ALLOWED));

if (violations.length > 0) {
    violations.forEach(({ file, line, token, rule }) => console.error(`${file}:${line}  ${token}  (${rule})`));
    console.error(`\n${violations.length} class(es) outside the design system. Use a token, or declare a justified exception in scripts/check-design-tokens.mjs.`);
    process.exit(1);
}

console.log('Design tokens: no raw palette class, arbitrary size or arbitrary colour.');
