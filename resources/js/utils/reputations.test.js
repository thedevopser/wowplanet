import { describe, it, expect } from 'vitest';
import { betterElsewhere, effectiveStanding, sortFactions, standingKey, standingLabel } from './reputations';

const faction = (overrides = {}) => ({
    id: 1, name: 'Faction', standing_name: 'Honoré', tier: 5, raw: 14000, renown_level: 0, completed: false, account_wide: false, ...overrides,
});

describe('effectiveStanding', () => {
    it('keeps the standing of a character-bound faction', () => {
        const own = faction();

        expect(effectiveStanding(own, { raw: 99999, tier: 7, standing_name: 'Exalté' })).toBe(own);
    });

    it('keeps an account-wide faction without better standing elsewhere', () => {
        const own = faction({ account_wide: true });

        expect(effectiveStanding(own, null)).toBe(own);
        expect(effectiveStanding(own, { raw: 100, renown_level: 0 })).toBe(own);
    });

    it('takes the best renown of the account for an account-wide faction', () => {
        const own = faction({ account_wide: true, renown_level: 5, standing_name: 'Renom 5' });

        expect(effectiveStanding(own, { renown_level: 12, raw: 0, tier: 0, completed: false })).toEqual(expect.objectContaining({
            renown_level: 12, standing_name: 'Renom 12', started: true,
        }));
    });

    it('always prefers the account standing for an unstarted account-wide faction', () => {
        const own = faction({ account_wide: true, started: false, raw: 0 });

        expect(effectiveStanding(own, { renown_level: 0, raw: 10, tier: 3, standing_name: 'Neutre', completed: false })).toEqual(expect.objectContaining({
            started: true, tier: 3, standing_name: 'Neutre',
        }));
    });
});

describe('betterElsewhere', () => {
    it('names another character with a higher standing on a character-bound faction', () => {
        const best = { character_name: 'Jaina', raw: 20000, standing_name: 'Révéré' };

        expect(betterElsewhere(faction(), best, 'Arthas')).toBe(best);
    });

    it.each([
        ['an account-wide faction', faction({ account_wide: true }), { character_name: 'Jaina', raw: 20000 }],
        ['no standing elsewhere', faction(), null],
        ['the character itself', faction(), { character_name: 'Arthas', raw: 20000 }],
        ['a standing that is not higher', faction(), { character_name: 'Jaina', raw: 14000 }],
    ])('names nobody for %s', (_, own, best) => {
        expect(betterElsewhere(own, best, 'Arthas')).toBeNull();
    });
});

describe('sortFactions', () => {
    it('puts the started factions first, unfinished before finished, then by name', () => {
        const factions = [
            faction({ id: 1, name: 'Beta', started: true, completed: true }),
            faction({ id: 2, name: 'Alpha', started: false }),
            faction({ id: 3, name: 'Gamma', started: true, completed: false }),
            faction({ id: 4, name: 'Delta', started: true, completed: false }),
        ];

        expect(sortFactions(factions, (entry) => entry).map((entry) => entry.name)).toEqual(['Delta', 'Gamma', 'Beta', 'Alpha']);
    });

    it('sorts by name alone when nothing tells whether a faction is started', () => {
        const factions = [faction({ name: 'Bêtes', completed: false }), faction({ name: 'Artisans', completed: true })];

        expect(sortFactions(factions, (entry) => entry).map((entry) => entry.name)).toEqual(['Artisans', 'Bêtes']);
    });
});

describe('standingKey', () => {
    it.each([
        [faction({ tier: 5 }), '5'],
        [faction({ renown_level: 10, completed: false }), 'renown'],
        [faction({ renown_level: 25, completed: true }), '7'],
        [faction({ started: false }), null],
        [faction({ tier: 8, standing_name: 'Génie', completed: true }), '7'],
        [faction({ tier: 79, standing_name: 'Niveau 80', completed: true }), '7'],
        [faction({ tier: 80, standing_name: 'Niveau 81', completed: false }), 'renown'],
        [faction({ tier: 7, standing_name: 'Exalté', completed: true }), '7'],
    ])('keys the colour of %o', (standing, expected) => {
        expect(standingKey(standing)).toBe(expected);
    });
});

describe('standingLabel', () => {
    it('keeps the name of a standing', () => {
        expect(standingLabel(faction({ standing_name: 'Honoré' }))).toBe('Honoré');
    });

    it('says that a companion level is the last one', () => {
        expect(standingLabel(faction({ tier: 79, standing_name: 'Niveau 80', completed: true }))).toBe('Niveau 80 · max');
    });

    it('says nothing more of a level still in progress', () => {
        expect(standingLabel(faction({ tier: 80, standing_name: 'Niveau 81', completed: false }))).toBe('Niveau 81');
    });

    it('names an unstarted faction so', () => {
        expect(standingLabel(faction({ started: false, standing_name: 'Neutre' }))).toBe('Non commencée');
    });
});
