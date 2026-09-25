import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const THEME_KEY = 'wowplanet-theme';
export const THEME_CHOICES = Object.freeze(['system', 'dark', 'light']);

const SYSTEM = 'system';
const DARK = 'dark';
const LIGHT = 'light';
const DARK_QUERY = '(prefers-color-scheme: dark)';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

export class InvalidThemeChoiceError extends Error {
    constructor(value) {
        super(`Invalid theme choice: ${JSON.stringify(value)}`);
        this.name = 'InvalidThemeChoiceError';
    }
}

/*
 * Module state is only ever written in the browser (event handlers and startThemeSync),
 * so a server render never leaks one visitor's theme into another's.
 */
const clientChoice = ref(null);
const systemPrefersDark = ref(null);
let syncing = false;

const isChoice = (value) => THEME_CHOICES.includes(value);

function readCookie() {
    return document.cookie.match(new RegExp(`(?:^|; )${THEME_KEY}=([^;]*)`))?.[1] ?? null;
}

function writeCookie(value) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${THEME_KEY}=${value}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax${secure}`;
}

function resolve(choice) {
    if (choice !== SYSTEM) {
        return choice;
    }

    return systemPrefersDark.value === false ? LIGHT : DARK;
}

function applyToDocument(choice) {
    document.documentElement.classList.toggle(DARK, resolve(choice) === DARK);
}

export function useTheme() {
    const page = usePage();

    const choice = computed(() => {
        if (clientChoice.value !== null) {
            return clientChoice.value;
        }

        return isChoice(page.props.theme) ? page.props.theme : SYSTEM;
    });

    const effective = computed(() => resolve(choice.value));

    function setChoice(value) {
        if (!isChoice(value)) {
            throw new InvalidThemeChoiceError(value);
        }

        clientChoice.value = value;
        localStorage.setItem(THEME_KEY, value);
        writeCookie(value);
        applyToDocument(value);
    }

    function toggle() {
        setChoice(effective.value === DARK ? LIGHT : DARK);
    }

    return { choice, effective, setChoice, toggle };
}

export function startThemeSync() {
    if (syncing) {
        return;
    }
    syncing = true;

    const media = window.matchMedia(DARK_QUERY);
    systemPrefersDark.value = media.matches;

    const fromCookie = readCookie();
    const stored = isChoice(fromCookie) ? fromCookie : localStorage.getItem(THEME_KEY);

    if (isChoice(stored)) {
        clientChoice.value = stored;
        if (fromCookie !== stored) {
            writeCookie(stored);
        }
    }

    const current = () => clientChoice.value ?? SYSTEM;

    media.addEventListener('change', (event) => {
        systemPrefersDark.value = event.matches;
        applyToDocument(current());
    });

    applyToDocument(current());
}
