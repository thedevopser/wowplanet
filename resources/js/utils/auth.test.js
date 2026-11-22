import { describe, it, expect } from 'vitest';
import { loginUrl } from './auth';

describe('loginUrl', () => {
    it('starts the login from a french page without naming its language', () => {
        expect(loginUrl('fr')).toBe('/auth/blizzard/redirect');
    });

    it('names the language of an english page, to come back to it after the login', () => {
        expect(loginUrl('en')).toBe('/auth/blizzard/redirect?lang=en');
    });
});
