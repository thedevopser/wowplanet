import { describe, it, expect } from 'vitest';
import { pvpBracketLabel, pvpGroupLabel, pvpShortLabel } from './pvpLabels';

const t = (key) => ({
    'pvp.group.arena': 'Arena',
    'pvp.group.shuffle': 'Solo Shuffle',
    'pvp.bracket.2v2': 'Arena 2v2',
    'pvp.all_specs': 'All specs',
})[key] ?? key;

describe('pvpLabels', () => {
    it('names a group by its key', () => {
        expect(pvpGroupLabel({ key: 'arena', label: 'Arène' }, t)).toBe('Arena');
    });

    it('names the brackets from their slug', () => {
        expect(pvpBracketLabel({ slug: '2v2', label: 'Arène 2c2' }, t)).toBe('Arena 2v2');
        expect(pvpBracketLabel({ slug: 'shuffle-priest-shadow', spec: 'Shadow', label: 'Mêlée solo — Ombre' }, t)).toBe('Solo Shuffle — Shadow');
        expect(pvpBracketLabel({ slug: 'shuffle-overall', label: 'Mêlée solo — Toutes spés' }, t)).toBe('Solo Shuffle — All specs');
        expect(pvpBracketLabel({ slug: 'shuffle-mage-fire', label: 'Mêlée solo — Feu' }, t)).toBe('Solo Shuffle — Feu');
        expect(pvpBracketLabel({ slug: 'unknown', label: 'Autre' }, t)).toBe('Autre');
    });

    it('names the short label of a bracket option', () => {
        expect(pvpShortLabel({ slug: '2v2', short: 'Arène 2c2' }, t)).toBe('Arena 2v2');
        expect(pvpShortLabel({ slug: 'shuffle-overall', short: 'Toutes spés' }, t)).toBe('All specs');
        expect(pvpShortLabel({ slug: 'shuffle-mage-fire', short: 'Fire' }, t)).toBe('Fire');
    });
});
