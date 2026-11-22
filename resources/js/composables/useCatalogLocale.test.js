import { describe, it, expect } from 'vitest';
import { defineComponent } from 'vue';
import { mountWithPlugins } from '../tests/helpers';
import { useCatalogLocale } from './useCatalogLocale.js';
import { translateCategory, translateSource } from '../utils/collections.js';

async function catalogLocaleOf(locale) {
    let catalogLocale;
    await mountWithPlugins(defineComponent({
        setup() {
            catalogLocale = useCatalogLocale();

            return () => null;
        },
    }), { locale });

    return catalogLocale;
}

describe('useCatalogLocale', () => {
    it.each([
        ['fr', 'Loup des bois', 'https://www.wowhead.com/fr/spell=1'],
        ['en', 'Timber Wolf', 'https://www.wowhead.com/spell=1'],
    ])('shows the name and the Wowhead link of the %s page', async (locale, name, link) => {
        const { nameOf, wowheadLink } = await catalogLocaleOf(locale);

        expect(nameOf({ name_fr: 'Loup des bois', name_en: 'Timber Wolf' })).toBe(name);
        expect(wowheadLink('spell=1')).toBe(link);
    });

    it('falls back to the french name of an entry not yet translated', async () => {
        const { nameOf } = await catalogLocaleOf('en');

        expect(nameOf({ name_fr: 'Loup des bois', name_en: null })).toBe('Loup des bois');
    });

    it('translates the curated taxonomy for the french pages only', async () => {
        const french = await catalogLocaleOf('fr');
        expect(french.categoryLabel('mounts', 'Racial')).toBe(translateCategory('mounts', 'Racial'));
        expect(french.sourceLabel('mounts', 'Vendor')).toBe(translateSource('mounts', 'Vendor'));

        const english = await catalogLocaleOf('en');
        expect(english.categoryLabel('mounts', 'Racial')).toBe('Racial');
        expect(english.sourceLabel('mounts', 'Vendor')).toBe('Vendor');
    });
});
