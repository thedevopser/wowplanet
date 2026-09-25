import { describe, it, expect } from 'vitest';
import { formatDuration } from './formatDuration';

describe('formatDuration', () => {
    it.each([
        [0, '0 s'],
        [42, '42 s'],
        [252, '4 min 12 s'],
        [7_925, '2 h 12 min'],
    ])('reads %i seconds as %s', (seconds, expected) => {
        expect(formatDuration(seconds)).toBe(expected);
    });

    it.each([[null], [undefined], [-1], [Number.NaN]])('shows a dash for %s', value => {
        expect(formatDuration(value)).toBe('—');
    });
});
