import { describe, it, expect } from 'vitest';
import { bestRunsByTiming, dungeonCards, formatRunDuration, seasonStats, uniqueDungeonCount, defaultResilienceTarget, remainingDungeons } from './mythicRuns';

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

const resilience = (level) => ({
    level,
    min_level: 12,
    max_level: 25,
    dungeons: [
        { dungeon_id: 249, name: 'Repos des rois', best_timed_level: 13 },
        { dungeon_id: 250, name: 'Temple de Sephraliss', best_timed_level: null },
        { dungeon_id: 399, name: 'Bassins', best_timed_level: 11 },
    ],
    targets: [
        { level: 12, remaining: [250, 399] },
        { level: 13, remaining: [250, 399] },
        { level: 14, remaining: [249, 250, 399] },
    ],
});

describe('defaultResilienceTarget', () => {
    it('aims at the lowest level for a character without resilience', () => {
        expect(defaultResilienceTarget(resilience(null))).toBe(12);
    });

    it('aims one level above the resilience reached', () => {
        expect(defaultResilienceTarget(resilience(12))).toBe(13);
        expect(defaultResilienceTarget(resilience(24))).toBe(25);
    });

    it('stays at the ceiling once it is reached', () => {
        expect(defaultResilienceTarget(resilience(25))).toBe(25);
    });
});

describe('remainingDungeons', () => {
    it('gives the dungeons left for a level, with their best run in time, in the order of the target', () => {
        expect(remainingDungeons(resilience(null), 12)).toEqual([
            { dungeon_id: 250, name: 'Temple de Sephraliss', best_timed_level: null },
            { dungeon_id: 399, name: 'Bassins', best_timed_level: 11 },
        ]);
    });

    it('gives nothing for a level the profile does not carry', () => {
        expect(remainingDungeons(resilience(null), 30)).toEqual([]);
    });

    it('leaves out a dungeon the season does not list', () => {
        const broken = { ...resilience(null), targets: [{ level: 12, remaining: [250, 9999] }] };

        expect(remainingDungeons(broken, 12).map((dungeon) => dungeon.dungeon_id)).toEqual([250]);
    });
});
