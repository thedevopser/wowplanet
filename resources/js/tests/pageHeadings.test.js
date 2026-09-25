// @vitest-environment node
import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const PAGES_DIR = resolve(__dirname, '../pages');
// Components that render the h1 of the page that uses them.
const H1_COMPONENTS = ['DatabasePageHeader', 'EditorialPage', 'AdminPageHeader'];

const pages = readdirSync(PAGES_DIR).filter((file) => file.endsWith('.vue'));

function templateOf(source) {
    return source.slice(source.indexOf('<template>'), source.lastIndexOf('</template>'));
}

function countH1(template) {
    const own = (template.match(/<h1[\s>]/g) ?? []).length;
    const delegated = H1_COMPONENTS.reduce((total, name) => total + (template.match(new RegExp(`<${name}[\\s>]`, 'g')) ?? []).length, 0);

    return own + delegated;
}

describe.each(pages)('%s', (file) => {
    it('carries a h1 naming its subject', () => {
        const template = templateOf(readFileSync(resolve(PAGES_DIR, file), 'utf8'));

        expect(countH1(template)).toBeGreaterThanOrEqual(1);
    });
});

describe.each(pages.filter((file) => file.startsWith('Admin')))('%s', (file) => {
    it('takes its title from the common header of the panel', () => {
        const template = templateOf(readFileSync(resolve(PAGES_DIR, file), 'utf8'));

        expect(template).toMatch(/<AdminPageHeader[\s>]/);
        expect(template).not.toMatch(/<h1[\s>]/);
    });
});
