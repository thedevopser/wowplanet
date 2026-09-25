import { describe, it, expect } from 'vitest';
import { formatScore } from './formatScore';

describe('formatScore', () => {
    it.each([
        [25.8, '25,8'],
        [25.84, '25,8'],
        [42, '42'],
        [99.96, '100'],
        [0, '0'],
    ])('writes %s as %s, one decimal at most, the French way', (score, expected) => {
        expect(formatScore(score)).toBe(expected);
    });
});
