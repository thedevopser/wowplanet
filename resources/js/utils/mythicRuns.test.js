import { describe, it, expect } from 'vitest';
import { bestRunsByTiming, dungeonCards, formatRunDuration, seasonStats, uniqueDungeonCount } from './mythicRuns';

const run = (dungeon, level, timed) => ({ dungeon_id: dungeon, level, is_timed: timed });

describe('bestRunsByTiming', () => {
    it('splits the runs done in time from the others', () => {
        const { timed, untimed } = bestRunsByTiming([run(1, 10, true), run(2, 9, false)]);

        expect(timed.map((entry) => entry.dungeon_id)).toEqual([1]);
        expect(untimed.map((entry) => entry.dungeon_id)).toEqual([2]);
    });

    it('keeps the highest level of each dungeon in each column', () => {
        const { timed, untimed } = bestRunsByTiming([run(1, 10, true), run(1, 12, true), run(1, 14, false), run(1, 13, false)]);

        expect(timed.map((entry) => entry.level)).toEqual([12]);
        expect(untimed.map((entry) => entry.level)).toEqual([14]);
    });

    it('sorts each column from the highest level down', () => {
        const { timed } = bestRunsByTiming([run(1, 8, true), run(2, 12, true), run(3, 10, true)]);

        expect(timed.map((entry) => entry.level)).toEqual([12, 10, 8]);
    });

    it('copes with no run at all', () => {
        expect(bestRunsByTiming(undefined)).toEqual({ timed: [], untimed: [] });
    });
});

describe('uniqueDungeonCount', () => {
    it('counts each dungeon once', () => {
        expect(uniqueDungeonCount([run(1, 10, true), run(1, 11, false), run(2, 9, true)])).toBe(2);
        expect(uniqueDungeonCount(undefined)).toBe(0);
    });
});

describe('formatRunDuration', () => {
    it.each([
        [1920000, '32:00'],
        [1925500, '32:05'],
        [59000, '0:59'],
    ])('writes %s ms as %s', (ms, expected) => {
        expect(formatRunDuration(ms)).toBe(expected);
    });
});

describe('dungeonCards', () => {
    const named = (dungeon, name, level, timed) => ({ dungeon_id: dungeon, dungeon_name: name, level, is_timed: timed });

    it('makes one card per dungeon, led by its best timed run', () => {
        const cards = dungeonCards([named(1, 'Ara-Kara', 10, true), named(1, 'Ara-Kara', 12, false), named(1, 'Ara-Kara', 9, true)]);

        expect(cards).toHaveLength(1);
        expect(cards[0].name).toBe('Ara-Kara');
        expect(cards[0].lead.level).toBe(10);
        expect(cards[0].other.level).toBe(12);
    });

    it('leads with the untimed run of a dungeon never done in time', () => {
        const [card] = dungeonCards([named(2, 'Stonevault', 11, false)]);

        expect(card.lead.level).toBe(11);
        expect(card.other).toBeNull();
    });

    it('sorts the dungeons by the level of their lead run, then by name', () => {
        const cards = dungeonCards([named(1, 'Bêta', 10, true), named(2, 'Alpha', 10, true), named(3, 'Gamma', 14, true)]);

        expect(cards.map((card) => card.name)).toEqual(['Gamma', 'Alpha', 'Bêta']);
    });
});

describe('seasonStats', () => {
    it('counts the dungeons, the highest key done in time and the dungeons done in time', () => {
        const runs = [
            { dungeon_id: 1, level: 12, is_timed: false },
            { dungeon_id: 1, level: 10, is_timed: true },
            { dungeon_id: 2, level: 11, is_timed: true },
            { dungeon_id: 3, level: 9, is_timed: false },
        ];

        expect(seasonStats(runs)).toEqual({ dungeons: 3, highestTimed: 11, timedDungeons: 2 });
    });

    it('has no highest key without a run in time', () => {
        expect(seasonStats([{ dungeon_id: 1, level: 12, is_timed: false }]).highestTimed).toBeNull();
    });
});
