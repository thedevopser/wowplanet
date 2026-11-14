// Links of the game objects to their page on Wowhead, in French.

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
