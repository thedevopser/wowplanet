import { describe, it, expect } from 'vitest';
import { contrastRatio, InvalidHexColorError, relativeLuminance } from './contrast';

describe('relativeLuminance', () => {
    it('spans from zero for black to one for white', () => {
        expect(relativeLuminance('#000000')).toBe(0);
        expect(relativeLuminance('#FFFFFF')).toBe(1);
    });

    it('weighs green far above red and red above blue, as the eye does', () => {
        expect(relativeLuminance('#00FF00')).toBeCloseTo(0.7152, 4);
        expect(relativeLuminance('#FF0000')).toBeCloseTo(0.2126, 4);
        expect(relativeLuminance('#0000FF')).toBeCloseTo(0.0722, 4);
    });

    it('treats very dark channels linearly below the sRGB knee', () => {
        expect(relativeLuminance('#0A0A0A')).toBeCloseTo(10 / 255 / 12.92, 6);
    });

    it('applies the sRGB gamma curve above the knee', () => {
        expect(relativeLuminance('#808080')).toBeCloseTo(0.2158605, 6);
    });
});

describe('contrastRatio', () => {
    it('rates black on white at the maximum of 21', () => {
        expect(contrastRatio('#000000', '#FFFFFF')).toBe(21);
    });

    it('rates a colour against itself at the minimum of 1', () => {
        expect(contrastRatio('#D4A844', '#D4A844')).toBe(1);
    });

    it('gives the same ratio whichever colour is passed first', () => {
        expect(contrastRatio('#1B1E27', '#F7F4EC')).toBe(contrastRatio('#F7F4EC', '#1B1E27'));
    });

    it('matches the reference ratio of mid grey on white', () => {
        expect(contrastRatio('#767676', '#FFFFFF')).toBeCloseTo(4.54, 2);
    });

    it('reads short hex notation and ignores case', () => {
        expect(contrastRatio('#fff', '#000')).toBe(21);
        expect(contrastRatio('#d4a844', '#D4A844')).toBe(1);
    });

    it.each(['', 'FFFFFF', '#FFFF', '#GGGGGG', '#1234567', 'white'])('refuses %j as a colour', (value) => {
        expect(() => contrastRatio(value, '#000000')).toThrow(InvalidHexColorError);
    });

    it('names the rejected value in the error', () => {
        expect(() => relativeLuminance('rouge')).toThrow('"rouge"');
    });

    it('refuses a value that is not a string', () => {
        expect(() => relativeLuminance(null)).toThrow(InvalidHexColorError);
    });
});
