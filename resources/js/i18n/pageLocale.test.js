import { afterEach, describe, expect, it } from 'vitest';
import axios from 'axios';
import { applyPageLocale, LOCALE_HEADER } from './pageLocale';

describe('applyPageLocale', () => {
    afterEach(() => {
        delete axios.defaults.headers.common[LOCALE_HEADER];
        delete window.whTooltips;
    });

    it('tells the api the language of the page', () => {
        applyPageLocale('en');

        expect(axios.defaults.headers.common[LOCALE_HEADER]).toBe('en');
    });

    it('asks Wowhead for tooltips in the language of the page', () => {
        window.whTooltips = { colorLinks: true, locale: 'fr' };

        applyPageLocale('en');
        expect(window.whTooltips).toEqual({ colorLinks: true, locale: 'en' });

        applyPageLocale('fr');
        expect(window.whTooltips.locale).toBe('fr');
    });
});
