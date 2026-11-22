import localizedRoutes from './localizedRoutes.json';

export const DEFAULT_LOCALE = 'fr';
const ENGLISH_PREFIX = 'en';
const PARAMETER = /^\{(\w+)(\?)?\}$/;

export function localeOfPath(path) {
    return path.replace(/^\/+/, '').split('/')[0] === ENGLISH_PREFIX ? 'en' : DEFAULT_LOCALE;
}

function segmentsOf(path) {
    return path.split('/').filter((segment) => segment !== '');
}

function matchPattern(pattern, path) {
    const regex = segmentsOf(pattern)
        .map((segment) => {
            const parameter = segment.match(PARAMETER);
            if (!parameter) {
                return `/${segment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`;
            }

            return parameter[2] ? '(?:/([^/]+))?' : '/([^/]+)';
        })
        .join('');
    const match = `/${segmentsOf(path).join('/')}`.match(new RegExp(`^${regex || '/'}$`));

    return match ? match.slice(1).map((value) => value ?? '') : null;
}

function buildPattern(pattern, values) {
    let position = 0;
    const built = segmentsOf(pattern)
        .map((segment) => {
            if (!PARAMETER.test(segment)) {
                return `/${segment}`;
            }
            const value = values[position++];

            return value ? `/${value}` : '';
        })
        .join('');

    return built || '/';
}

/**
 * Same page in the target locale, from the table shared with the server. A path outside
 * the table (admin, authentication, external link) is returned untouched.
 */
export function localizePath(path, targetLocale) {
    const [, pathname, suffix] = path.match(/^([^?#]*)(.*)$/);
    const sourceLocale = localeOfPath(pathname);

    for (const patterns of Object.values(localizedRoutes)) {
        const values = matchPattern(patterns[sourceLocale], pathname);
        if (values) {
            return buildPattern(patterns[targetLocale], values) + suffix;
        }
    }

    return path;
}
