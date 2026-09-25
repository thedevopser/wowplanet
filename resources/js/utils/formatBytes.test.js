import { describe, it, expect } from 'vitest';
import { formatBytes } from './formatBytes';

describe('formatBytes', () => {
    it('leaves a handful of bytes as bytes', () => {
        expect(formatBytes(0)).toBe('0 o');
        expect(formatBytes(512)).toBe('512 o');
    });

    it('climbs to the next unit once a thousand is reached', () => {
        expect(formatBytes(1024)).toBe('1 ko');
        expect(formatBytes(157_683)).toBe('154 ko');
    });

    it('keeps a decimal where the unit is coarse enough for it to matter', () => {
        expect(formatBytes(45_641_330)).toBe('43,5 Mo');
        expect(formatBytes(1_283_486)).toBe('1,2 Mo');
    });

    it('goes all the way to gigabytes, which the store is heading for', () => {
        expect(formatBytes(104_857_600)).toBe('100 Mo');
        expect(formatBytes(2_147_483_648)).toBe('2 Go');
    });

    it('reads a missing or unusable size as nothing rather than showing NaN', () => {
        expect(formatBytes(null)).toBe('—');
        expect(formatBytes(undefined)).toBe('—');
        expect(formatBytes('beaucoup')).toBe('—');
    });

    it('refuses to invent a size for a negative number', () => {
        expect(formatBytes(-1)).toBe('—');
    });
});
