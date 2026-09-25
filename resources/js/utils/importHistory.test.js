import { describe, it, expect } from 'vitest';
import { formatHistoryDate, formatSigned, triggerLabel, MODES } from './importHistory';

describe('importHistory', () => {
    it('names the console as such, and anyone else by what the server recorded', () => {
        expect(triggerLabel('console')).toBe('Console');
        expect(triggerLabel('12345')).toBe('12345');
    });

    it('names both modes', () => {
        expect(MODES).toEqual({ incremental: 'Incrémental', forced: 'Forcé' });
    });

    it('dates an import to the minute', () => {
        expect(formatHistoryDate('2026-09-22T10:05:08+00:00')).toMatch(/22\/09\/2026/u);
    });

    it('signs a change in volume, and leaves a missing one blank', () => {
        expect(formatSigned(1200).replace(/\s/gu, ' ')).toBe('+1 200');
        expect(formatSigned(-400)).toBe('−400');
        expect(formatSigned(0)).toBe('0');
        expect(formatSigned(null)).toBe('—');
    });
});
