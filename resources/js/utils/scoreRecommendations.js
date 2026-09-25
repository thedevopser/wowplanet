// The categories closest to completion, with what is still missing: the "Il vous reste"
// block of the score, for a character as for the whole account.

export const MAX_ITEMS_SHOWN = 20;
export const MAX_RECOMMENDATIONS = 12;

const WOWHEAD = 'https://www.wowhead.com/fr';

const search = (item) => `${WOWHEAD}/search?q=${encodeURIComponent(item.name)}`;

const WOWHEAD_PATHS = Object.freeze({
    mount: (item) => (item.wowhead_id ? `${WOWHEAD}/spell=${item.wowhead_id}` : search(item)),
    pet: (item) => (item.creature_id ? `${WOWHEAD}/npc=${item.creature_id}` : search(item)),
    quest: (item) => `${WOWHEAD}/quest=${item.id}`,
    achievement: (item) => `${WOWHEAD}/achievement=${item.id}`,
    decor: (item) => (item.item_id ? `${WOWHEAD}/item=${item.item_id}` : search(item)),
});

export function wowheadUrl(type, item) {
    return (WOWHEAD_PATHS[type] ?? search)(item);
}

const COLLECTIONS = Object.freeze([
    { field: 'mounts', type: 'mount', dimension: 'Montures', dimensionKey: 'mounts' },
    { field: 'pets', type: 'pet', dimension: 'Mascottes', dimensionKey: 'pets' },
    { field: 'decor', type: 'decor', dimension: 'Décorations', dimensionKey: 'decor' },
]);

function recommendation(key, name, type, dimension, dimensionKey, items) {
    const missing = items.filter((item) => !item.is_completed);
    const completed = items.length - missing.length;

    if (missing.length === 0 || completed === 0) {
        return null;
    }

    return {
        key,
        name,
        dimension,
        dimensionKey,
        completed,
        total: items.length,
        missing: missing.length,
        percent: (completed / items.length) * 100,
        missingItems: missing.slice(0, MAX_ITEMS_SHOWN).map((item) => ({ id: item.id, name: item.name, wowheadUrl: wowheadUrl(type, item) })),
        missingMore: Math.max(0, missing.length - MAX_ITEMS_SHOWN),
    };
}

function bySource(items) {
    const groups = new Map();
    for (const item of items) {
        if (!item.source) {
            continue;
        }
        groups.set(item.source, [...(groups.get(item.source) ?? []), item]);
    }

    return groups;
}

function collectionRecommendations(profile) {
    return COLLECTIONS.flatMap(({ field, type, dimension, dimensionKey }) => [...bySource(profile[field] ?? [])]
        .map(([source, items]) => recommendation(`${type}:${source}`, source, type, dimension, dimensionKey, items)));
}

function expansionRecommendations(profile) {
    return Object.entries(profile.collections ?? {}).flatMap(([expansionId, expansion]) => [
        ...(expansion?.achievements?.categories ?? []).map((category) => recommendation(
            `ach:${expansionId}:${category.name}`, category.name, 'achievement', 'Hauts-faits', 'achievements', category.items ?? [],
        )),
        ...(expansion?.quests?.zones ?? []).map((zone) => recommendation(
            `quest:${expansionId}:${zone.name}`, zone.name, 'quest', 'Quêtes', 'quests', zone.items ?? [],
        )),
    ]);
}

export function buildRecommendations(profile) {
    if (!profile) {
        return [];
    }

    return [...collectionRecommendations(profile), ...expansionRecommendations(profile)]
        .filter(Boolean)
        .sort((a, b) => a.missing - b.missing || b.percent - a.percent)
        .slice(0, MAX_RECOMMENDATIONS);
}
