// Tailwind's default palette is switched off in app.css: a raw class would silently render nothing.
const PALETTES = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black';
const COLOUR_UTILITIES = 'bg|text|border|ring|outline|fill|stroke|shadow|from|via|to|decoration|divide|placeholder|accent|caret';

const RULES = Object.freeze([
    { rule: 'raw-palette', pattern: new RegExp(`^(?:${COLOUR_UTILITIES})-(?:${PALETTES})(?:-\\d{2,3})?(?:/\\d{1,3})?$`) },
    { rule: 'arbitrary-size', pattern: /^[a-z-]+-\[[\d.]+(?:px|rem|em)\]$/ },
    { rule: 'arbitrary-colour', pattern: /^[a-z-]+-\[#[0-9a-f]{3,8}\]$/i },
]);

const SEPARATORS = /[\s"'`{}(),;<>=]+/;

export class UndeclaredExceptionError extends Error {
    constructor(exception) {
        super(`The design token exception ${JSON.stringify(exception)} needs a file, a token and a reason.`);
        this.name = 'UndeclaredExceptionError';
    }
}

function assertDeclared(exception) {
    if (!exception.file || !exception.token || !exception.reason) {
        throw new UndeclaredExceptionError(exception);
    }
}

// Variants (hover:, dark:, data-[state=open]:) do not change what a class paints.
const utilityOf = (candidate) => candidate.replace(/^!/, '').split(':').pop();

export function findTokenViolations(source, file, allowed = []) {
    allowed.forEach(assertDeclared);
    const excused = new Set(allowed.filter((exception) => exception.file === file).map((exception) => exception.token));

    return source.split('\n').flatMap((text, index) => text.split(SEPARATORS)
        .map(utilityOf)
        .filter((token) => token && !excused.has(token))
        .flatMap((token) => RULES.filter(({ pattern }) => pattern.test(token)).map(({ rule }) => ({ file, line: index + 1, token, rule }))));
}
