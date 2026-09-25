import { describe, it, expect, beforeEach } from 'vitest';
import { nextTick, reactive } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { useToastStore } from '../stores/toasts';
import { watchFlashMessages } from './useFlashToasts';

let page;
let toasts;

beforeEach(() => {
    setActivePinia(createPinia());
    toasts = useToastStore();
    page = reactive({ props: { flash: { success: null, error: null } } });
});

describe('watchFlashMessages', () => {
    it('shows the flash already on the page', () => {
        page.props.flash = { success: null, error: 'Connexion impossible' };

        watchFlashMessages(page, toasts);

        expect(toasts.items).toEqual([{ id: 1, title: 'Connexion impossible', description: '', tone: 'error' }]);
    });

    it('shows a success message as a success toast', () => {
        page.props.flash = { success: 'Préférences enregistrées', error: null };

        watchFlashMessages(page, toasts);

        expect(toasts.items[0].tone).toBe('success');
    });

    it('shows nothing without a flash message', () => {
        watchFlashMessages(page, toasts);

        expect(toasts.items).toEqual([]);
    });

    it('tolerates a page without flash props', () => {
        page.props = {};

        watchFlashMessages(page, toasts);

        expect(toasts.items).toEqual([]);
    });

    it('shows the flash brought by a later visit', async () => {
        watchFlashMessages(page, toasts);

        page.props = { flash: { success: null, error: 'Accès refusé' } };
        await nextTick();

        expect(toasts.items.map((toast) => toast.title)).toEqual(['Accès refusé']);
    });

    it('does not show the same flash twice while the page keeps it', async () => {
        page.props.flash = { success: null, error: 'Connexion impossible' };
        watchFlashMessages(page, toasts);

        page.props.url = '/faq';
        await nextTick();

        expect(toasts.items).toHaveLength(1);
    });

    it('stops watching once asked to', async () => {
        const stop = watchFlashMessages(page, toasts);

        stop();
        page.props = { flash: { success: 'Plus tard', error: null } };
        await nextTick();

        expect(toasts.items).toEqual([]);
    });

    it('asks to sign in, with a way to do it, when the server requires it', () => {
        page.props.flash = { success: null, error: null, authRequired: true };

        watchFlashMessages(page, toasts);

        expect(toasts.items).toEqual([{
            id: 1,
            title: 'Connectez-vous pour accéder à cette page',
            description: 'Cette page affiche les données de votre compte Battle.net.',
            tone: 'info',
            action: { label: 'Se connecter', href: '/auth/blizzard/redirect' },
        }]);
    });
});
