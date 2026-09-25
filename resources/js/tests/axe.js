import { expect } from 'vitest';
import { configureAxe } from 'vitest-axe';

// happy-dom computes no colour: contrast is guarded by themeTokens.test.js on the tokens themselves.
const pageAxe = configureAxe({ rules: { 'color-contrast': { enabled: false } } });

// A page is mounted without its layout, whose landmarks (main, header, footer) are checked on the layout itself.
const fragmentAxe = configureAxe({
    rules: {
        'color-contrast': { enabled: false },
        region: { enabled: false },
        'landmark-one-main': { enabled: false },
    },
});

export async function expectNoAxeViolations(element, { landmarks = false } = {}) {
    const { violations } = await (landmarks ? pageAxe : fragmentAxe)(element);

    expect(violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);
}
