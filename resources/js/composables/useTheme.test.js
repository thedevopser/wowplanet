import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const page = vi.hoisted(() => ({ props: {} }));

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

const COOKIE = 'wowplanet-theme';
const root = document.documentElement;
let mediaListeners;

function preferSystemDark(dark) {
    mediaListeners = [];
    window.matchMedia = vi.fn(() => ({
        matches: dark,
        addEventListener: (event, listener) => mediaListeners.push(listener),
    }));
}

function systemTurns(dark) {
    mediaListeners.forEach((listener) => listener({ matches: dark }));
}

function clearCookie() {
    document.cookie = `${COOKIE}=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT`;
}

function cookieValue() {
    return document.cookie.match(/(?:^|; )wowplanet-theme=([^;]*)/)?.[1] ?? null;
}

async function loadTheme() {
    vi.resetModules();

    return import('./useTheme.js');
}

beforeEach(() => {
    page.props = {};
    clearCookie();
    localStorage.clear();
    root.classList.remove('dark');
    preferSystemDark(false);
});

afterEach(() => {
    clearCookie();
    localStorage.clear();
});

describe('useTheme before the page is mounted', () => {
    it('takes the choice the server read from the cookie', async () => {
        page.props.theme = 'light';
        const { useTheme } = await loadTheme();

        const { choice, effective } = useTheme();

        expect(choice.value).toBe('light');
        expect(effective.value).toBe('light');
    });

    it('follows the system when the server knows no choice', async () => {
        const { useTheme } = await loadTheme();

        expect(useTheme().choice.value).toBe('system');
    });

    it('reads an unknown server value as following the system', async () => {
        page.props.theme = 'purple';
        const { useTheme } = await loadTheme();

        expect(useTheme().choice.value).toBe('system');
    });

    it('renders the system choice as dark until the real preference is known, as the server does', async () => {
        const { useTheme } = await loadTheme();

        expect(useTheme().effective.value).toBe('dark');
    });
});

describe('startThemeSync', () => {
    it('resolves the system choice from the real preference', async () => {
        const { useTheme, startThemeSync } = await loadTheme();

        startThemeSync();

        expect(useTheme().effective.value).toBe('light');
        expect(root.classList.contains('dark')).toBe(false);
    });

    it('follows a change of the system preference while the page is open', async () => {
        const { useTheme, startThemeSync } = await loadTheme();
        startThemeSync();

        systemTurns(true);

        expect(useTheme().effective.value).toBe('dark');
        expect(root.classList.contains('dark')).toBe(true);
    });

    it('leaves an explicit choice alone when the system preference changes', async () => {
        page.props.theme = 'light';
        document.cookie = `${COOKIE}=light; path=/`;
        const { useTheme, startThemeSync } = await loadTheme();
        startThemeSync();

        systemTurns(true);

        expect(useTheme().effective.value).toBe('light');
        expect(root.classList.contains('dark')).toBe(false);
    });

    it('keeps the choice of a visitor who only has it in local storage, and writes it to the cookie', async () => {
        localStorage.setItem(COOKIE, 'dark');
        const { useTheme, startThemeSync } = await loadTheme();

        startThemeSync();

        expect(useTheme().choice.value).toBe('dark');
        expect(cookieValue()).toBe('dark');
        expect(root.classList.contains('dark')).toBe(true);
    });

    it('ignores an unknown value left in local storage', async () => {
        localStorage.setItem(COOKIE, 'purple');
        const { useTheme, startThemeSync } = await loadTheme();

        startThemeSync();

        expect(useTheme().choice.value).toBe('system');
        expect(cookieValue()).toBeNull();
    });

    it('listens to the system preference only once however often it is started', async () => {
        const { startThemeSync } = await loadTheme();

        startThemeSync();
        startThemeSync();

        expect(mediaListeners).toHaveLength(1);
    });
});

describe('setChoice', () => {
    it('applies, remembers and sends the new choice to the server', async () => {
        const { useTheme, startThemeSync } = await loadTheme();
        startThemeSync();
        const { choice, effective, setChoice } = useTheme();

        setChoice('dark');

        expect(choice.value).toBe('dark');
        expect(effective.value).toBe('dark');
        expect(root.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem(COOKIE)).toBe('dark');
        expect(cookieValue()).toBe('dark');
    });

    it('can go back to following the system', async () => {
        page.props.theme = 'dark';
        const { useTheme, startThemeSync } = await loadTheme();
        startThemeSync();

        useTheme().setChoice('system');

        expect(useTheme().effective.value).toBe('light');
        expect(cookieValue()).toBe('system');
    });

    it('marks the cookie secure on a site served over https', async () => {
        const written = [];
        vi.spyOn(document, 'cookie', 'set').mockImplementation((value) => written.push(value));
        vi.spyOn(window, 'location', 'get').mockReturnValue({ protocol: 'https:' });
        const { useTheme } = await loadTheme();

        useTheme().setChoice('dark');

        vi.restoreAllMocks();
        expect(written.at(-1)).toBe('wowplanet-theme=dark; path=/; max-age=31536000; SameSite=Lax; Secure');
    });

    it('refuses a value outside the three choices', async () => {
        const { useTheme } = await loadTheme();

        expect(() => useTheme().setChoice('purple')).toThrow('"purple"');
        expect(localStorage.getItem(COOKIE)).toBeNull();
    });

    it('shares the choice between every caller', async () => {
        const { useTheme } = await loadTheme();

        useTheme().setChoice('light');

        expect(useTheme().choice.value).toBe('light');
    });
});

describe('toggle', () => {
    it('switches a dark page to light', async () => {
        page.props.theme = 'dark';
        const { useTheme } = await loadTheme();

        useTheme().toggle();

        expect(useTheme().choice.value).toBe('light');
    });

    it('switches a light page to dark', async () => {
        page.props.theme = 'light';
        const { useTheme } = await loadTheme();

        useTheme().toggle();

        expect(useTheme().choice.value).toBe('dark');
    });

    it('turns a system-driven light page into an explicit dark choice', async () => {
        const { useTheme, startThemeSync } = await loadTheme();
        startThemeSync();

        useTheme().toggle();

        expect(useTheme().choice.value).toBe('dark');
    });
});
