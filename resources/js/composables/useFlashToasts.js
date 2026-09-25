import { watch } from 'vue';
import { LOGIN_URL } from '../utils/auth';

const TONE_BY_FLASH_KEY = Object.freeze({ success: 'success', error: 'error' });

const SIGN_IN_REQUIRED = Object.freeze({
    title: 'Connectez-vous pour accéder à cette page',
    description: 'Cette page affiche les données de votre compte Battle.net.',
    tone: 'info',
    action: { label: 'Se connecter', href: LOGIN_URL },
});

function showFlash(flash, toasts) {
    if (flash?.authRequired === true) {
        toasts.show(SIGN_IN_REQUIRED);
    }

    for (const [key, tone] of Object.entries(TONE_BY_FLASH_KEY)) {
        const message = flash?.[key];
        if (typeof message === 'string' && message !== '') {
            toasts.show({ title: message, tone });
        }
    }
}

// A session flash lives for a single response: every visit brings a new flash object,
// so watching its identity shows each message once.
export function watchFlashMessages(page, toasts) {
    return watch(() => page.props.flash, (flash) => showFlash(flash, toasts), { immediate: true });
}
