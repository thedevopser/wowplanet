// The "À faire" list of the account arrives sorted from the server; the page only narrows it
// to a dimension or to what one character can do.

import { dimensionColor } from './wowColors';

export const ALL = 'tout';

const ACCOUNT_SCOPE = 'account';

// Mirror of TodoDimension.
const DIMENSION_LABELS = Object.freeze({
    mounts: 'Montures',
    pets: 'Mascottes',
    decor: 'Décorations',
    achievements: 'Hauts-faits',
    quests: 'Quêtes',
    reputations: 'Réputations',
    recipes: 'Recettes',
});

// The recipes have no colour of their own: they are the professions of the score.
const COLOR_DIMENSIONS = Object.freeze({ recipes: 'professions' });

export function todoColor(dimension) {
    return dimensionColor(COLOR_DIMENSIONS[dimension] ?? dimension);
}

export class UnknownTodoDimensionError extends Error {
    constructor(dimension) {
        super(`Unknown todo dimension: ${dimension}`);
        this.name = 'UnknownTodoDimensionError';
    }
}

export function dimensionLabel(dimension) {
    if (!Object.hasOwn(DIMENSION_LABELS, dimension)) {
        throw new UnknownTodoDimensionError(dimension);
    }

    return DIMENSION_LABELS[dimension];
}

export function dimensionSummaries(categories) {
    const summaries = new Map();
    for (const category of categories) {
        const summary = summaries.get(category.dimension) ?? { dimension: category.dimension, label: dimensionLabel(category.dimension), categories: 0, missing: 0 };
        summaries.set(category.dimension, { ...summary, categories: summary.categories + 1, missing: summary.missing + category.missing });
    }

    return [...summaries.values()];
}

export function expansionOptions(categories, expansionNames) {
    const counts = new Map();
    for (const category of categories) {
        if (category.expansionId !== null) {
            counts.set(category.expansionId, (counts.get(category.expansionId) ?? 0) + 1);
        }
    }

    if (counts.size === 0) {
        return [];
    }

    const latestFirst = [...counts].sort(([first], [second]) => second - first);

    return [
        { value: ALL, label: 'Toutes', count: categories.length },
        ...latestFirst.map(([expansionId, count]) => ({ value: String(expansionId), label: expansionNames.get(expansionId) ?? String(expansionId), count })),
    ];
}

// The server sends the categories closest to the end first.
export function nearlyDone(categories, count) {
    return categories.slice(0, count);
}

export function characterOptions(characters) {
    return [{ value: ALL, label: 'Tous les personnages' }, ...characters.map((name) => ({ value: name, label: name }))];
}

const NAMED_DOERS = 3;

export function doersLabel(doers, roster) {
    if (doers.scope === ACCOUNT_SCOPE) {
        return 'Tout le compte';
    }

    const { characters } = doers;

    if (roster.length > 1 && roster.every((name) => characters.includes(name))) {
        return 'Tous vos personnages';
    }

    const named = characters.slice(0, NAMED_DOERS).join(', ');
    const others = characters.length - NAMED_DOERS;

    return others > 0 ? `${named} et ${others} autre${others > 1 ? 's' : ''}` : named;
}

const doableBy = (character) => (item) => item.doers.scope === ACCOUNT_SCOPE || item.doers.characters.includes(character);

function forCharacter(category, character) {
    const missingItems = category.missingItems.filter(doableBy(character));

    if (missingItems.length === category.missingItems.length) {
        return category;
    }

    // The items beyond those sent are unknown: who can do them cannot be told.
    return { ...category, missingItems, missingMore: 0 };
}

const inExpansion = (expansion) => (category) => expansion === ALL || String(category.expansionId) === expansion;

export function filterTodo(categories, { dimension, character, expansion = ALL }) {
    const inDimension = categories
        .filter((category) => dimension === ALL || category.dimension === dimension)
        .filter(inExpansion(expansion));

    if (character === ALL) {
        return inDimension;
    }

    return inDimension.map((category) => forCharacter(category, character)).filter((category) => category.missingItems.length > 0);
}
