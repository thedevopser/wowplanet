import { watch } from 'vue';

const TONE_BY_FLASH_KEY = Object.freeze({ success: 'success', error: 'error' });

const signInRequired = ({ t, loginUrl }) => ({
    title: t('flash.sign_in_required.title'),
    description: t('flash.sign_in_required.description'),
    tone: 'info',
    action: { label: t('flash.sign_in_required.action'), href: loginUrl },
});

function showFlash(flash, toasts, options) {
    if (flash?.authRequired === true) {
        toasts.show(signInRequired(options));
    }

    for (const [key, tone] of Object.entries(TONE_BY_FLASH_KEY)) {
        const message = flash?.[key];
        if (typeof message === 'string' && message !== '') {
            toasts.show({ title: message, tone });
        }
    }
}

// A session flash lives for a single response: every visit brings a new flash object,
// so watching its identity shows each message once. The server writes its own messages
// in the language of the page; `t` and `loginUrl` speak that language for the others.
export function watchFlashMessages(page, toasts, options) {
    return watch(() => page.props.flash, (flash) => showFlash(flash, toasts, options), { immediate: true });
}
