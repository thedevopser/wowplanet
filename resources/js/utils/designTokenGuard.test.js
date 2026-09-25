import { describe, it, expect } from 'vitest';
import { findTokenViolations } from './designTokenGuard';

const violations = (source, file = 'components/Example.vue', allowed = []) => findTokenViolations(source, file, allowed);

describe('findTokenViolations', () => {
    it.each(['text-slate-400', 'bg-red-500/10', 'border-white/10', 'text-white', 'hover:bg-blue-600', 'from-amber-300', 'placeholder-slate-500'])(
        'refuses the raw palette class %s',
        (className) => {
            expect(violations(`<p class="flex ${className}">x</p>`)).toEqual([
                { file: 'components/Example.vue', line: 1, token: className.replace(/^hover:/, ''), rule: 'raw-palette' },
            ]);
        },
    );

    it.each(['text-[11px]', 'w-[240px]', 'max-w-[42rem]', 'p-[1.5em]', 'size-[18px]'])('refuses the arbitrary size %s', (className) => {
        expect(violations(`<div class="${className}"></div>`)).toEqual([
            { file: 'components/Example.vue', line: 1, token: className, rule: 'arbitrary-size' },
        ]);
    });

    it('refuses an arbitrary colour', () => {
        expect(violations('<div class="bg-[#1e293b]"></div>')[0]).toMatchObject({ token: 'bg-[#1e293b]', rule: 'arbitrary-colour' });
    });

    it.each([
        'text-muted bg-surface border-strong text-on-brand-discord bg-night-950/60',
        'data-[state=open]:animate-fade-in has-[:focus-visible]:outline-2',
        'grid-cols-[15rem_minmax(0,1fr)] grid-rows-[0fr] transition-[width]',
        'top-[calc(var(--spacing-header)+1.5rem)] min-h-[60vh]',
        'rounded-ui-md text-2xl tabular-nums bg-transparent border-transparent text-current',
    ])('accepts the tokens, state variants and layout templates of the design system: %s', (classes) => {
        expect(violations(`<div class="${classes}"></div>`)).toEqual([]);
    });

    it('gives the line of each offending class', () => {
        expect(violations('<div>\n  <p class="text-muted">a</p>\n  <p class="text-gray-500">b</p>\n</div>')).toEqual([
            { file: 'components/Example.vue', line: 3, token: 'text-gray-500', rule: 'raw-palette' },
        ]);
    });

    it('does not mistake words of the text for classes', () => {
        expect(violations('<p>Pour aller de la page to-do list, on translate le texte.</p>')).toEqual([]);
    });

    it('lets a declared exception through, and only in its own file', () => {
        const allowed = [{ file: 'components/Legacy.vue', token: 'text-white', reason: 'kept on purpose' }];

        expect(violations('<p class="text-white"></p>', 'components/Legacy.vue', allowed)).toEqual([]);
        expect(violations('<p class="text-white"></p>', 'components/Other.vue', allowed)).toHaveLength(1);
    });

    it('refuses an exception that does not say why it exists', () => {
        expect(() => violations('', 'components/Legacy.vue', [{ file: 'components/Legacy.vue', token: 'text-white' }])).toThrow('reason');
    });
});
