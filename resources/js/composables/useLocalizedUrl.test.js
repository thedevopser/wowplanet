import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useLocalizedUrl } from './useLocalizedUrl.js';

const page = vi.hoisted(() => ({ props: {} }));

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

beforeEach(() => {
    page.props = {};
});

describe('useLocalizedUrl', () => {
    it('builds internal links in the locale of the page', () => {
        page.props.locale = 'en';

        expect(useLocalizedUrl().localizedUrl('/base-de-donnees/montures')).toBe('/en/database/mounts');
    });

    it('keeps the french links on a french page', () => {
        page.props.locale = 'fr';

        expect(useLocalizedUrl().localizedUrl('/base-de-donnees/montures')).toBe('/base-de-donnees/montures');
    });

    it('falls back to french when the page shares no locale', () => {
        expect(useLocalizedUrl().locale.value).toBe('fr');
    });

    it('follows the locale when the page changes', () => {
        page.props.locale = 'fr';
        const { locale } = useLocalizedUrl();

        page.props.locale = 'en';

        expect(locale.value).toBe('en');
    });
});
