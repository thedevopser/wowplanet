import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it, expect } from 'vitest';
import { COLLECTIONS, UNCATEGORIZED, groupCollection, translateCategory, translateSource } from './collections';

const item = (id, category, source, done = false, extra = {}) => ({ id, name: `Objet ${id}`, category, source, is_completed: done, ...extra });

describe('COLLECTIONS', () => {
    it('describes the three collections of the sheet', () => {
        expect(Object.keys(COLLECTIONS)).toEqual(['mounts', 'pets', 'decor']);
        expect(COLLECTIONS.mounts).toEqual(expect.objectContaining({ field: 'mounts', title: 'Montures', dimension: 'mounts' }));
        expect(COLLECTIONS.decor).toEqual(expect.objectContaining({ field: 'decor', title: 'Décorations', dimension: 'decor' }));
    });

    it.each([
        ['mounts', { wowhead_id: 42, name: 'X' }, 'https://www.wowhead.com/fr/spell=42'],
        ['pets', { wowhead_id: 7, name: 'X' }, 'https://www.wowhead.com/fr/npc=7'],
        ['decor', { item_id: 5, name: 'X' }, 'https://www.wowhead.com/fr/item=5'],
        ['mounts', { name: 'Drake de feu' }, 'https://www.wowhead.com/fr/search?q=Drake%20de%20feu'],
    ])('links a %s item to Wowhead', (kind, entry, expected) => {
        expect(COLLECTIONS[kind].wowheadUrl(entry)).toBe(expected);
    });
});

describe('translations', () => {
    it('translates a known category and keeps an expansion name', () => {
        expect(translateCategory('mounts', 'World Events')).toBe('Événements mondiaux');
        expect(translateCategory('mounts', 'The War Within')).toBe('The War Within');
    });

    it('translates a known source of each collection', () => {
        expect(translateSource('mounts', 'Raid Drop')).toBe('Butin de raid');
        expect(translateSource('pets', 'Zone Drop')).toBe('Butin de zone');
        expect(translateSource('decor', 'Vendor')).toBe('Vendeur');
    });

    it.each([
        ['Renown: Dornogal', 'Renom : Dornogal'],
        ['Trading Post: Janvier', 'Comptoir : Janvier'],
        ['War Within: Delves', 'The War Within : Delves'],
        ['Midnight: Prey', 'Midnight : Prey'],
    ])('reads the prefix of %s', (source, expected) => {
        expect(translateSource('pets', source)).toBe(expected);
    });

    it('keeps an unknown source as it is', () => {
        expect(translateSource('decor', 'Somewhere new')).toBe('Somewhere new');
    });
});

describe('groupCollection', () => {
    const order = ['The War Within', 'Dragonflight', 'Mounts'];

    it('orders the categories as asked, then the unknown ones, then the uncategorised items', () => {
        const groups = groupCollection([
            item(1, 'Mounts', 'Vendor'),
            item(2, 'Zzz', 'Quest'),
            item(3, 'The War Within', 'Raid Drop', true),
            item(4, null, null),
        ], order);

        expect(groups.map((category) => category.name)).toEqual(['The War Within', 'Mounts', 'Zzz', UNCATEGORIZED]);
    });

    it('counts each category and groups it by source, sources by name', () => {
        const [first] = groupCollection([
            item(1, 'Dragonflight', 'Vendor', true),
            item(2, 'Dragonflight', 'Achievement'),
            item(3, 'Dragonflight', 'Vendor'),
        ], order);

        expect(first).toEqual(expect.objectContaining({ name: 'Dragonflight', completed: 1, total: 3 }));
        expect(first.sources.map((source) => [source.name, source.completed, source.total])).toEqual([['Achievement', 0, 1], ['Vendor', 1, 2]]);
    });

    it('keeps an item without category or source apart, in a single group', () => {
        const groups = groupCollection([item(1, 'Mounts', null, true), item(2, null, 'Vendor')], order);

        expect(groups).toEqual([expect.objectContaining({ name: UNCATEGORIZED, completed: 1, total: 2, sources: [] })]);
        expect(groups[0].items.map((entry) => entry.id)).toEqual([1, 2]);
    });

    it('gives nothing for an empty collection', () => {
        expect(groupCollection([], order)).toEqual([]);
    });
});

describe('versioned taxonomy snapshot', () => {
    const SNAPSHOT = resolve(import.meta.dirname, '../../../database/data/collection_taxonomy.csv');
    const KIND_BY_ENTITY = { mount: 'mounts', pet: 'pets', decor: 'decor' };

    // Expansion names stay in English, as in the French client.
    const EXPANSIONS = new Set([
        'Classic', 'The Burning Crusade', 'Burning Crusade', 'Wrath of the Lich King', 'Cataclysm', 'Mists of Pandaria',
        'Warlords of Draenor', 'Legion', 'Battle for Azeroth', 'Shadowlands', 'Dragonflight', 'The War Within', 'Midnight',
    ]);

    // A curation placeholder, to be fixed by arbitration rather than translated.
    const PLACEHOLDERS = new Set(['TODO']);

    const parseLine = (line) => [...line.matchAll(/(?:^|,)("(?:[^"]|"")*"|[^,]*)/g)]
        .map(([, field]) => field.replace(/^"|"$/g, '').replaceAll('""', '"'));

    const labels = (column) => {
        const [, ...lines] = readFileSync(SNAPSHOT, 'utf8').trim().split('\n');
        const pairs = lines.map(parseLine).map((fields) => [KIND_BY_ENTITY[fields[0]], fields[column]]);

        return [...new Set(pairs.filter(([, label]) => label).map((pair) => pair.join('|')))]
            .map((pair) => pair.split('|'));
    };

    const isHandled = (kind, label, vocabulary, translate) => EXPANSIONS.has(label)
        || PLACEHOLDERS.has(label)
        || Object.hasOwn(COLLECTIONS[kind].labels[vocabulary], label)
        || translate(kind, label) !== label;

    it('gives every category a French label', () => {
        const missing = labels(2).filter(([kind, label]) => !isHandled(kind, label, 'categories', translateCategory));

        expect(missing).toEqual([]);
    });

    it('gives every source a French label', () => {
        const missing = labels(3).filter(([kind, label]) => !isHandled(kind, label, 'sources', translateSource));

        expect(missing).toEqual([]);
    });
});
