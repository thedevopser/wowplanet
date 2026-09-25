// @vitest-environment node
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';

const PAGES = ['AdminDashboardPage', 'AdminImportsPage', 'AdminReferencePage'];

describe.each(PAGES)('%s', (page) => {
    it('follows its imports in the common tracking card', () => {
        const source = readFileSync(resolve(__dirname, `../pages/${page}.vue`), 'utf8');

        expect(source).toMatch(/<ImportTrackingCard :tracking="tracking" \/>/);
        expect(source).not.toMatch(/<ImportRunPanel[\s>]/);
    });
});
