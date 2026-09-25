import { describe, it, expect } from 'vitest';
import { MAX_ITEMS_SHOWN, MAX_RECOMMENDATIONS, buildRecommendations, wowheadUrl } from './scoreRecommendations';

const item = (id, source, done, extra = {}) => ({ id, name: `Objet ${id}`, source, is_completed: done, ...extra });

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

describe('buildRecommendations', () => {
    it('gives nothing without a profile', () => {
        expect(buildRecommendations(null)).toEqual([]);
    });

    it('groups a collection by source, keeping only the groups started and unfinished', () => {
        const recs = buildRecommendations({
            mounts: [item(1, 'Raid', true), item(2, 'Raid', false), item(3, 'Vendeur', true), item(4, 'Quête', false), item(5, null, false)],
        });

        expect(recs).toEqual([{
            key: 'mount:Raid',
            name: 'Raid',
            dimension: 'Montures',
            dimensionKey: 'mounts',
            completed: 1,
            total: 2,
            missing: 1,
            percent: 50,
            missingItems: [{ id: 2, name: 'Objet 2', wowheadUrl: 'https://www.wowhead.com/fr/search?q=Objet%202' }],
            missingMore: 0,
        }]);
    });

    it('reads achievement categories and quest zones of every expansion', () => {
        const recs = buildRecommendations({
            collections: {
                10: {
                    achievements: { categories: [{ name: 'Donjons', items: [item(1, null, true), item(2, null, false)] }] },
                    quests: { zones: [{ name: 'Durotar', items: [item(3, null, true), item(4, null, false)] }] },
                },
            },
        });

        expect(recs.map((rec) => [rec.key, rec.dimensionKey])).toEqual([['ach:10:Donjons', 'achievements'], ['quest:10:Durotar', 'quests']]);
    });

    it('puts first the groups closest to completion', () => {
        const recs = buildRecommendations({
            pets: [item(1, 'A', true), item(2, 'A', false), item(3, 'A', false), item(4, 'B', true), item(5, 'B', false)],
        });

        expect(recs.map((rec) => rec.name)).toEqual(['B', 'A']);
    });

    it('keeps a dozen recommendations at most', () => {
        const mounts = Array.from({ length: 20 }, (_, index) => [item(index * 2, `S${index}`, true), item(index * 2 + 1, `S${index}`, false)]).flat();

        expect(buildRecommendations({ mounts })).toHaveLength(MAX_RECOMMENDATIONS);
    });

    it('lists a limited number of missing items, and counts the rest', () => {
        const decor = [item(0, 'Maison', true), ...Array.from({ length: MAX_ITEMS_SHOWN + 5 }, (_, index) => item(index + 1, 'Maison', false))];

        const [rec] = buildRecommendations({ decor });

        expect(rec.missingItems).toHaveLength(MAX_ITEMS_SHOWN);
        expect(rec.missingMore).toBe(5);
    });
});
