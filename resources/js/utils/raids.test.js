import { describe, it, expect } from 'vitest';
import { DIFFICULTIES, bossMatrix, difficultySummaries, sortNewestFirst } from './raids';

const mode = (type, completed, total, encounters) => ({ difficulty_type: type, difficulty_label: type, completed_count: completed, total_count: total, encounters });
const boss = (id, name, at) => ({ id, name, last_kill_timestamp: at });

const RAID = {
    instance_id: 1,
    instance_name: 'L’abîme Venimeux',
    modes: [
        mode('HEROIC', 2, 8, [boss(1, 'Nek’zali', 30), boss(2, 'Sentinelles', 40)]),
        mode('NORMAL', 4, 8, [boss(1, 'Nek’zali', 10), boss(2, 'Sentinelles', 11), boss(3, 'Vashnik', 12), boss(4, 'Autel', 13)]),
    ],
};

describe('DIFFICULTIES', () => {
    it('lists the four difficulties in game order, each with its quality colour', () => {
        expect(DIFFICULTIES.map((difficulty) => [difficulty.type, difficulty.label, difficulty.short, difficulty.quality])).toEqual([
            ['LFR', 'Outil Raids', 'LFR', 'UNCOMMON'],
            ['NORMAL', 'Normal', 'N', 'RARE'],
            ['HEROIC', 'Héroïque', 'H', 'EPIC'],
            ['MYTHIC', 'Mythique', 'M', 'LEGENDARY'],
        ]);
    });
});

describe('difficultySummaries', () => {
    it('sums up every difficulty, the ones not started included', () => {
        expect(difficultySummaries(RAID)).toEqual([
            { type: 'LFR', label: 'Outil Raids', short: 'LFR', quality: 'UNCOMMON', started: false, completed: 0, total: 0, cleared: false },
            { type: 'NORMAL', label: 'Normal', short: 'N', quality: 'RARE', started: true, completed: 4, total: 8, cleared: false },
            { type: 'HEROIC', label: 'Héroïque', short: 'H', quality: 'EPIC', started: true, completed: 2, total: 8, cleared: false },
            { type: 'MYTHIC', label: 'Mythique', short: 'M', quality: 'LEGENDARY', started: false, completed: 0, total: 0, cleared: false },
        ]);
    });

    it('marks a difficulty whose every boss is down as cleared', () => {
        const summaries = difficultySummaries({ modes: [mode('LFR', 6, 6, [])] });

        expect(summaries[0].cleared).toBe(true);
    });
});

describe('bossMatrix', () => {
    it('keeps the difficulties started, in game order', () => {
        expect(bossMatrix(RAID).difficulties).toEqual(['NORMAL', 'HEROIC']);
    });

    it('lists each boss once, in the order of the difficulty that knows most of them', () => {
        expect(bossMatrix(RAID).rows.map((row) => row.name)).toEqual(['Nek’zali', 'Sentinelles', 'Vashnik', 'Autel']);
    });

    it('gives the last kill of each boss in each difficulty, or nothing', () => {
        const [first, , third] = bossMatrix(RAID).rows;

        expect(first.kills).toEqual({ NORMAL: 10, HEROIC: 30 });
        expect(third.kills).toEqual({ NORMAL: 12, HEROIC: null });
    });

    it('counts the bosses never defeated, whose names Blizzard does not give', () => {
        expect(bossMatrix(RAID).unknownCount).toBe(4);
    });

    it('copes with a raid never started', () => {
        expect(bossMatrix({ modes: [] })).toEqual({ difficulties: [], rows: [], unknownCount: 0 });
    });
});

describe('sortNewestFirst', () => {
    it('puts first the most recent raid, Blizzard numbering its instances as they come out', () => {
        const sorted = sortNewestFirst([{ instance_id: 1307 }, { instance_id: 1320 }, { instance_id: 1305 }, { instance_id: 1314 }]);

        expect(sorted.map((raid) => raid.instance_id)).toEqual([1320, 1314, 1307, 1305]);
    });

    it('leaves the given list untouched', () => {
        const raids = [{ instance_id: 1 }, { instance_id: 2 }];

        sortNewestFirst(raids);

        expect(raids.map((raid) => raid.instance_id)).toEqual([1, 2]);
    });
});
