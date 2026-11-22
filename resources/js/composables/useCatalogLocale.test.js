import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useCatalogLocale } from './useCatalogLocale.js';
import { translateCategory, translateSource } from '../utils/collections.js';

const page = vi.hoisted(() => ({ props: {} }));

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

beforeEach(() => {
    page.props = {};
});

describe('useCatalogLocale', () => {
    it.each([
        ['fr', 'Loup des bois', 'https://www.wowhead.com/fr/spell=1'],
        ['en', 'Timber Wolf', 'https://www.wowhead.com/spell=1'],
    ])('shows the name and the Wowhead link of the %s page', (locale, name, link) => {
        page.props.locale = locale;
        const { nameOf, wowheadLink } = useCatalogLocale();

        expect(nameOf({ name_fr: 'Loup des bois', name_en: 'Timber Wolf' })).toBe(name);
        expect(wowheadLink('spell=1')).toBe(link);
    });

    it('falls back to the french name of an entry not yet translated', () => {
        page.props.locale = 'en';

        expect(useCatalogLocale().nameOf({ name_fr: 'Loup des bois', name_en: null })).toBe('Loup des bois');
    });

    it('translates the curated taxonomy for the french pages only', () => {
        page.props.locale = 'fr';
        expect(useCatalogLocale().categoryLabel('mounts', 'Racial')).toBe(translateCategory('mounts', 'Racial'));
        expect(useCatalogLocale().sourceLabel('mounts', 'Vendor')).toBe(translateSource('mounts', 'Vendor'));

        page.props.locale = 'en';
        expect(useCatalogLocale().categoryLabel('mounts', 'Racial')).toBe('Racial');
        expect(useCatalogLocale().sourceLabel('mounts', 'Vendor')).toBe('Vendor');
    });
});
