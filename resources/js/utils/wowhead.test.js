import { describe, it, expect } from 'vitest';
import { wowheadLink, wowheadUrl } from './wowhead';

describe('wowheadUrl', () => {
    it.each([
        ['mount', { wowhead_id: 42, name: 'X' }, 'https://www.wowhead.com/fr/spell=42'],
        ['pet', { creature_id: 7, name: 'X' }, 'https://www.wowhead.com/fr/npc=7'],
        ['quest', { id: 3, name: 'X' }, 'https://www.wowhead.com/fr/quest=3'],
        ['achievement', { id: 9, name: 'X' }, 'https://www.wowhead.com/fr/achievement=9'],
        ['decor', { item_id: 5, name: 'X' }, 'https://www.wowhead.com/fr/item=5'],
        ['mount', { name: 'Proto-drake' }, 'https://www.wowhead.com/fr/search?q=Proto-drake'],
    ])('links a %s to its page', (type, entry, expected) => {
        expect(wowheadUrl(type, entry)).toBe(expected);
    });
});

describe('wowheadUrl in english', () => {
    it('links to the english pages of Wowhead', () => {
        expect(wowheadUrl('quest', { id: 3, name: 'X' }, 'en')).toBe('https://www.wowhead.com/quest=3');
        expect(wowheadLink('item=5', 'en')).toBe('https://www.wowhead.com/item=5');
        expect(wowheadLink('item=5', 'fr')).toBe('https://www.wowhead.com/fr/item=5');
    });
});
