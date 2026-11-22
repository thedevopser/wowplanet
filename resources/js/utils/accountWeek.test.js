import { describe, it, expect } from 'vitest';
import { difficultyLabel, hardestFirst, nextSlot, slotLabel, unitOf } from './accountWeek';

const slots = (rewards, thresholds) => thresholds.map((threshold, index) => ({ threshold, reached: rewards[index] !== undefined, reward: rewards[index] ?? null }));

describe('difficultyLabel', () => {
    it('names the raid difficulties as the site does', () => {
        expect(['LFR', 'NORMAL', 'HEROIC', 'MYTHIC'].map((difficulty) => difficultyLabel(difficulty))).toEqual(['LFR', 'Normal', 'Héroïque', 'Mythique']);
    });

    it('shows a difficulty it does not know as Blizzard sends it', () => {
        expect(difficultyLabel('TIMEWALKING')).toBe('TIMEWALKING');
    });
});

describe('hardestFirst', () => {
    it('puts the hardest difficulty first', () => {
        expect(hardestFirst([{ difficulty: 'LFR' }, { difficulty: 'HEROIC' }, { difficulty: 'MYTHIC' }]).map((entry) => entry.difficulty))
            .toEqual(['MYTHIC', 'HEROIC', 'LFR']);
    });
});

describe('slotLabel', () => {
    it('gives a raid slot the difficulty it is worth once reached, its threshold before', () => {
        const [first, , third] = slots(['HEROIC', 'NORMAL'], [2, 4, 6]);

        expect(slotLabel('raid', first)).toBe('Héroïque');
        expect(slotLabel('raid', third)).toBe('6 boss');
    });

    it('gives a Mythic+ slot the level it is worth once reached, its threshold before', () => {
        const [first, second] = slots([12], [1, 4, 8]);

        expect(slotLabel('mythicPlus', first)).toBe('+12');
        expect(slotLabel('mythicPlus', second)).toBe('4 donjons');
        expect(slotLabel('mythicPlus', { threshold: 1, reached: false, reward: null })).toBe('1 donjon');
    });
});

describe('nextSlot', () => {
    it('tells how many are missing for the next slot, and which slot it is', () => {
        expect(nextSlot({ count: 3, slots: slots(['HEROIC'], [2, 4, 6]) })).toEqual({ missing: 1, position: 2 });
    });

    it('has nothing left once every slot is reached', () => {
        expect(nextSlot({ count: 8, slots: slots([15, 12, 10], [1, 4, 8]) })).toBeNull();
    });
});

describe('unitOf', () => {
    it('counts bosses and dungeons in French', () => {
        expect([unitOf('raid', 1), unitOf('raid', 3), unitOf('mythicPlus', 1), unitOf('mythicPlus', 3)]).toEqual(['1 boss', '3 boss', '1 donjon', '3 donjons']);
    });
});
