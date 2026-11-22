import { describe, it, expect, beforeEach } from 'vitest';
import { rememberLocale } from './localeChoice.js';

beforeEach(() => {
    document.cookie = 'wowplanet-locale=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
});

describe('rememberLocale', () => {
    it('remembers the chosen language in a cookie the server reads', () => {
        rememberLocale('en');

        expect(document.cookie).toContain('wowplanet-locale=en');
    });

    it('refuses a language the site does not offer', () => {
        expect(() => rememberLocale('de')).toThrow('Unknown locale "de"');
    });
});
