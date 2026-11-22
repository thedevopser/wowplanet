import { describe, it, expect } from 'vitest';
import { localizePath, localeOfPath } from './localizePath.js';

describe('localeOfPath', () => {
    it.each([
        ['/', 'fr'],
        ['/base-de-donnees/montures', 'fr'],
        ['/en', 'en'],
        ['/en/database/mounts', 'en'],
        ['/entretien', 'fr'],
    ])('reads the locale of %s', (path, locale) => {
        expect(localeOfPath(path)).toBe(locale);
    });
});

describe('localizePath', () => {
    it.each([
        ['/', 'en', '/en'],
        ['/en', 'fr', '/'],
        ['/base-de-donnees/montures', 'en', '/en/database/mounts'],
        ['/base-de-donnees/montures/racial', 'en', '/en/database/mounts/racial'],
        ['/en/database/mounts/racial', 'fr', '/base-de-donnees/montures/racial'],
        ['/base-de-donnees/montures', 'fr', '/base-de-donnees/montures'],
        ['/mon-compte/score', 'en', '/en/my-account/score'],
        ['/cgu', 'en', '/en/terms'],
    ])('translates %s into %s', (path, locale, expected) => {
        expect(localizePath(path, locale)).toBe(expected);
    });

    it('keeps the query string and the fragment', () => {
        expect(localizePath('/base-de-donnees/quetes?page=2#top', 'en')).toBe('/en/database/quests?page=2#top');
    });

    it('keeps parameter values that look like a translated segment', () => {
        expect(localizePath('/character/dalaran/montures', 'en')).toBe('/en/character/dalaran/montures');
    });

    it.each(['/admin/imports', '/auth/blizzard/redirect', 'https://www.wowhead.com/fr/item=1'])(
        'leaves %s untouched, since it has no translated version',
        (path) => {
            expect(localizePath(path, 'en')).toBe(path);
        },
    );
});
