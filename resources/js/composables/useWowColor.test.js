import { describe, it, expect, vi, beforeEach } from 'vitest';

const theme = vi.hoisted(() => ({ effective: null }));

vi.mock('./useTheme', async () => {
    const { ref } = await import('vue');
    theme.effective = ref('dark');

    return { useTheme: () => ({ effective: theme.effective }) };
});

import { classColor, factionColor } from '../utils/wowColors';
import { useWowColor } from './useWowColor';

beforeEach(() => {
    theme.effective.value = 'dark';
});

describe('useWowColor', () => {
    it('gives the readable variant of the dark theme', () => {
        const { readable } = useWowColor();

        expect(readable(classColor(7))).toBe(classColor(7).onDark);
    });

    it('follows the theme', () => {
        const { readable } = useWowColor();

        theme.effective.value = 'light';

        expect(readable(classColor(7))).toBe(classColor(7).onLight);
    });

    it('gives nothing for an unknown colour', () => {
        const { readable } = useWowColor();

        expect(readable(null)).toBeUndefined();
    });

    it('resolves a game value without failing on an unknown one', () => {
        const { safe } = useWowColor();

        expect(safe(factionColor, 'Horde')).toEqual(factionColor('HORDE'));
        expect(safe(factionColor, 'Neutre')).toBeNull();
        expect(safe(classColor, 99)).toBeNull();
    });
});
