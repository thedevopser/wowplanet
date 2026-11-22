import { describe, it, expect } from 'vitest';
import { dimensionColor } from './wowColors';
import { ALL, doersLabel, todoColor, dimensionLabel, characterOptions, filterTodo, nearlyDone, dimensionSummaries, expansionOptions } from './accountTodo';

const account = { scope: 'account', characters: [] };
const by = (...characters) => ({ scope: 'characters', characters });
const item = (id, doers) => ({ id, name: `Élément ${id}`, wowheadUrl: `https://www.wowhead.com/fr/quest=${id}`, detail: null, doers });
const category = (key, dimension, missingItems, missingMore = 0, expansionId = null) => ({
    key,
    name: key,
    dimension,
    expansionId,
    completed: 1,
    total: missingItems.length + missingMore + 1,
    missing: missingItems.length + missingMore,
    percent: 50,
    missingItems,
    missingMore,
});

const mounts = category('mounts:Butin', 'mounts', [item(1, account), item(2, account)], 5);
const quests = category('quests:10:Comté', 'quests', [item(3, by('Apery')), item(4, by('Apery', 'Jaina')), item(5, by('Jaina'))], 2, 10);
const recipes = category('recipes:10:Forge', 'recipes', [item(6, by('Jaina'))], 0, 10);
const categories = [mounts, quests, recipes];

describe('dimensionLabel', () => {
    it('names each dimension of the list by its translation key', () => {
        expect(['mounts', 'pets', 'decor', 'achievements', 'quests', 'reputations', 'recipes'].map(dimensionLabel))
            .toEqual(['score.dimension.mounts', 'score.dimension.pets', 'score.dimension.decor', 'score.dimension.achievements', 'score.dimension.quests', 'score.dimension.reputations', 'account.todo.recipes']);
    });

    it('refuses a dimension it does not know', () => {
        expect(() => dimensionLabel('raids')).toThrow('raids');
    });
});

describe('doersLabel', () => {
    const roster = ['Apery', 'Jaina', 'Thrall', 'Sylvanas', 'Anduin'];

    it('says the whole account for a collection or an achievement', () => {
        expect(doersLabel(account, roster)).toBe('Tout le compte');
    });

    it('names up to three characters', () => {
        expect(doersLabel(by('Apery', 'Jaina', 'Thrall'), roster)).toBe('Apery, Jaina, Thrall');
    });

    it('counts the characters beyond three', () => {
        expect(doersLabel(by('Apery', 'Jaina', 'Thrall', 'Sylvanas'), roster)).toBe('Apery, Jaina, Thrall et 1 autre');
    });

    it('says every character rather than naming them all', () => {
        expect(doersLabel(by(...roster), roster)).toBe('Tous vos personnages');
    });
});

describe('todoColor', () => {
    it('colours a dimension like the score does, the recipes like the professions', () => {
        expect(todoColor('recipes')).toEqual(dimensionColor('professions'));
        expect(todoColor('quests')).toEqual(dimensionColor('quests'));
    });
});

describe('characterOptions', () => {
    it('offers the whole account, then each character', () => {
        expect(characterOptions(['Apery', 'Jaina'])).toEqual([
            { value: ALL, label: 'Tous les personnages' },
            { value: 'Apery', label: 'Apery' },
            { value: 'Jaina', label: 'Jaina' },
        ]);
    });
});

describe('filterTodo', () => {
    it('keeps the whole list, in its order, without filter', () => {
        expect(filterTodo(categories, { dimension: ALL, character: ALL })).toEqual(categories);
    });

    it('keeps the categories of one dimension', () => {
        expect(filterTodo(categories, { dimension: 'quests', character: ALL })).toEqual([quests]);
    });

    it('keeps for a character what he can do, and what the whole account can do', () => {
        const filtered = filterTodo(categories, { dimension: ALL, character: 'Apery' });

        expect(filtered.map((entry) => entry.key)).toEqual(['mounts:Butin', 'quests:10:Comté']);
        expect(filtered[0]).toEqual(mounts);
        expect(filtered[1].missingItems.map((entry) => entry.id)).toEqual([3, 4]);
    });

    it('stops counting the hidden items of a category the character filter cut', () => {
        const [filteredQuests] = filterTodo([quests], { dimension: ALL, character: 'Jaina' });

        expect(filteredQuests.missingMore).toBe(0);
    });

    it('combines both filters', () => {
        expect(filterTodo(categories, { dimension: 'recipes', character: 'Apery' })).toEqual([]);
    });

    it('keeps the categories of one expansion', () => {
        const dragonflight = category('quests:9:Plaines', 'quests', [item(8, account)], 0, 9);

        expect(filterTodo([quests, dragonflight], { dimension: 'quests', character: ALL, expansion: '9' })).toEqual([dragonflight]);
    });
});

describe('nearlyDone', () => {
    it('takes the first categories of the list, which come closest to the end first', () => {
        expect(nearlyDone(categories, 2)).toEqual([mounts, quests]);
    });
});

describe('dimensionSummaries', () => {
    it('sums up each dimension in the order of the list: its categories and what they miss', () => {
        const otherMounts = category('mounts:Vendeur', 'mounts', [item(9, account)]);

        expect(dimensionSummaries([mounts, quests, otherMounts])).toEqual([
            { dimension: 'mounts', label: 'score.dimension.mounts', categories: 2, missing: 8 },
            { dimension: 'quests', label: 'score.dimension.quests', categories: 1, missing: 5 },
        ]);
    });
});

describe('expansionOptions', () => {
    const names = new Map([[9, 'Dragonflight'], [10, 'The War Within']]);

    it('offers the expansions of the categories, latest first, after all of them', () => {
        const dragonflight = category('quests:9:Plaines', 'quests', [item(8, account)], 0, 9);

        expect(expansionOptions([dragonflight, quests, recipes], names)).toEqual([
            { value: ALL, label: 'Toutes', count: 3 },
            { value: '10', label: 'The War Within', count: 2 },
            { value: '9', label: 'Dragonflight', count: 1 },
        ]);
    });

    it('offers nothing to choose for categories without expansion', () => {
        expect(expansionOptions([mounts], names)).toEqual([]);
    });
});
