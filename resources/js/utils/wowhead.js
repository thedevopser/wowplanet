// Links of the game objects to their page on Wowhead, in the language of the page.

const WOWHEAD_BY_LOCALE = Object.freeze({
    fr: 'https://www.wowhead.com/fr',
    en: 'https://www.wowhead.com',
});

export function wowheadLink(path, locale = 'fr') {
    return `${WOWHEAD_BY_LOCALE[locale] ?? WOWHEAD_BY_LOCALE.fr}/${path}`;
}

export const wowheadSearch = (name) => `search?q=${encodeURIComponent(name)}`;

const search = (item) => wowheadSearch(item.name);

const WOWHEAD_PATHS = Object.freeze({
    mount: (item) => (item.wowhead_id ? `spell=${item.wowhead_id}` : search(item)),
    pet: (item) => (item.creature_id ? `npc=${item.creature_id}` : search(item)),
    quest: (item) => `quest=${item.id}`,
    achievement: (item) => `achievement=${item.id}`,
    decor: (item) => (item.item_id ? `item=${item.item_id}` : search(item)),
});

export function wowheadUrl(type, item, locale = 'fr') {
    return wowheadLink((WOWHEAD_PATHS[type] ?? search)(item), locale);
}
