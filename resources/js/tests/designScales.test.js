// @vitest-environment node
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeAll, describe, it, expect } from 'vitest';
import { compile } from '@tailwindcss/node';

const CSS_DIRECTORY = resolve(__dirname, '../../css');
const css = readFileSync(resolve(CSS_DIRECTORY, 'app.css'), 'utf8');

let build;

beforeAll(async () => {
    ({ build } = await compile(css, { base: CSS_DIRECTORY, onDependency() {} }));
});

function ruleFor(output, className) {
    const start = output.indexOf(`.${className.replace(/[/.]/g, '\\$&')} {`);

    return start < 0 ? null : output.slice(start, output.indexOf('}', start) + 1).replace(/\s+/g, ' ');
}

function generated(className) {
    return ruleFor(build([className]), className);
}

function reducedMotionBlock() {
    const start = css.indexOf('@media (prefers-reduced-motion: reduce)');
    let depth = 0;

    for (let index = css.indexOf('{', start); index < css.length; index += 1) {
        depth += css[index] === '{' ? 1 : css[index] === '}' ? -1 : 0;
        if (depth === 0) {
            return css.slice(start, index + 1);
        }
    }

    return '';
}

describe('radius scale', () => {
    it.each([['rounded-ui-sm', '6px'], ['rounded-ui-md', '10px']])('%s rounds by %s', (className, value) => {
        expect(css).toMatch(new RegExp(`--radius-${className.slice('rounded-'.length)}:\\s*${value};`));
        expect(generated(className)).toContain('border-radius: var(--radius-');
    });

    it('leaves the radii of the screens not yet migrated untouched', () => {
        expect(css).not.toMatch(/--radius-(sm|md|lg|xl):/);
    });
});

describe('elevation scale', () => {
    it.each(['shadow-elevation-1', 'shadow-elevation-2'])('generates %s', (className) => {
        expect(generated(className)).toContain('box-shadow');
    });
});

describe('z-index scale', () => {
    it.each([
        ['base', '0'],
        ['sticky', '10'],
        ['header', '20'],
        ['overlay', '40'],
        ['dialog', '50'],
        ['toast', '60'],
    ])('z-%s stacks at %s', (name, value) => {
        expect(css).toMatch(new RegExp(`--z-index-${name}:\\s*${value};`));
        expect(generated(`z-${name}`)).toContain(`z-index: var(--z-index-${name})`);
    });
});

describe('motion scale', () => {
    it.each([['fast', '150ms'], ['base', '200ms'], ['slow', '300ms']])('duration-%s lasts %s', (name, value) => {
        expect(css).toMatch(new RegExp(`--transition-duration-${name}:\\s*${value};`));
        expect(generated(`duration-${name}`)).toContain(`--transition-duration-${name}`);
    });

    it.each(['ease-enter', 'ease-exit'])('generates the %s curve', (className) => {
        expect(generated(className)).toContain('transition-timing-function');
    });
});

describe('enter and exit animations', () => {
    it.each(['fade', 'dialog'])('%s enters over the base duration and leaves in 70 percent of it', (name) => {
        expect(css).toMatch(new RegExp(`--animate-${name}-in:\\s*${name}-in var\\(--transition-duration-base\\) var\\(--ease-enter\\)`));
        expect(css).toMatch(new RegExp(`--animate-${name}-out:\\s*${name}-out calc\\(var\\(--transition-duration-base\\) \\* 0\\.7\\) var\\(--ease-exit\\)`));
        expect(generated(`animate-${name}-in`)).toContain('animation');
        expect(build([`animate-${name}-in`])).toContain(`@keyframes ${name}-in`);
    });
});

describe('reduced motion', () => {
    it('brings every transition and animation down to nothing when the visitor asks for it', () => {
        const block = reducedMotionBlock();

        expect(block).toMatch(/animation-duration:\s*0s\s*!important/);
        expect(block).toMatch(/animation-iteration-count:\s*1\s*!important/);
        expect(block).toMatch(/transition-duration:\s*0s\s*!important/);
        expect(block).toMatch(/scroll-behavior:\s*auto\s*!important/);
    });

    it('keeps loading indicators visible, still and at a fixed opacity', () => {
        const block = reducedMotionBlock();
        const loaders = block.slice(block.indexOf('.animate-spin'));

        expect(loaders).toMatch(/^\.animate-spin,\s*\.animate-pulse\s*\{/);
        expect(loaders).toMatch(/animation:\s*none\s*!important/);
        expect(loaders).toMatch(/opacity:\s*0\.7\d*\s*!important/);
    });
});

describe('default focus ring', () => {
    it('draws the keyboard focus in the accent colour', () => {
        expect(css).toMatch(/\*:focus-visible\s*\{\s*outline: 2px solid var\(--color-accent\);/);
    });

    // Unlayered rules beat every utility: in the base layer, a component can still drop it.
    it('lives in the base layer, so that utilities can override it', () => {
        const baseLayer = css.match(/@layer base\s*\{([\s\S]*?)\n\}/)?.[1] ?? '';

        expect(baseLayer).toContain('*:focus-visible');
    });
});

describe('legacy theme', () => {
    it.each(['text-slate-400', 'bg-red-500', 'border-white/10', 'text-white', 'bg-black/50', 'from-blue-500'])(
        'generates nothing for the raw Tailwind palette class %s',
        (className) => {
            expect(generated(className)).toBeNull();
        },
    );

    it.each(['text-muted', 'bg-surface', 'border-strong', 'bg-night-950/60', 'text-on-brand-discord', 'bg-danger/10'])(
        'still generates the project colour %s',
        (className) => {
            expect(generated(className)).not.toBeNull();
        },
    );

    it('no longer overrides utility classes in light mode', () => {
        expect(css).not.toContain(':root:not(.dark)');
    });

    it('forces nothing outside the reduced-motion rule', () => {
        const outside = css.replace(reducedMotionBlock(), '');

        expect(outside).not.toContain('!important');
    });

    it.each(['card-glass', 'btn-gradient', '--bg-gradient', '--card-bg', '--text-primary', '--modal-bg', 'page-enter-active', 'spin-reverse'])(
        'drops the unused legacy rule %s',
        (legacy) => {
            expect(css).not.toContain(legacy);
        },
    );

    it('paints the document with the tokens of the theme', () => {
        expect(css).toMatch(/body\s*\{\s*@apply bg-background text-default antialiased;\s*\}/);
    });
});
