import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const COOKIE = 'wowplanet-theme';
const root = document.documentElement;

function preferSystemDark(dark) {
    window.matchMedia = vi.fn((query) => ({ matches: query === '(prefers-color-scheme: dark)' && dark }));
}

function clearCookie() {
    document.cookie = `${COOKIE}=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT`;
}

async function boot() {
    vi.resetModules();
    await import('./themeBoot.js');
}

beforeEach(() => {
    clearCookie();
    localStorage.clear();
    root.classList.remove('dark');
    preferSystemDark(false);
});

afterEach(() => {
    clearCookie();
    localStorage.clear();
});

describe('theme boot script', () => {
    it('follows a dark system preference when nothing was chosen', async () => {
        preferSystemDark(true);

        await boot();

        expect(root.classList.contains('dark')).toBe(true);
    });

    it('follows a light system preference when nothing was chosen', async () => {
        root.classList.add('dark');

        await boot();

        expect(root.classList.contains('dark')).toBe(false);
    });

    it('obeys an explicit choice stored in the cookie over the system preference', async () => {
        preferSystemDark(true);
        document.cookie = `${COOKIE}=light; path=/`;

        await boot();

        expect(root.classList.contains('dark')).toBe(false);
    });

    it('falls back on the choice kept in local storage by earlier visits', async () => {
        localStorage.setItem(COOKIE, 'dark');

        await boot();

        expect(root.classList.contains('dark')).toBe(true);
    });

    it('prefers the cookie to local storage', async () => {
        document.cookie = `${COOKIE}=dark; path=/`;
        localStorage.setItem(COOKIE, 'light');

        await boot();

        expect(root.classList.contains('dark')).toBe(true);
    });

    it('treats the system choice as following the system', async () => {
        preferSystemDark(true);
        document.cookie = `${COOKIE}=system; path=/`;

        await boot();

        expect(root.classList.contains('dark')).toBe(true);
    });

    it('ignores an unknown stored value and follows the system', async () => {
        preferSystemDark(true);
        document.cookie = `${COOKIE}=purple; path=/`;

        await boot();

        expect(root.classList.contains('dark')).toBe(true);
    });

    it('never breaks the page when storage is unavailable', async () => {
        const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });

        await expect(boot()).resolves.not.toThrow();

        getItem.mockRestore();
    });
});
