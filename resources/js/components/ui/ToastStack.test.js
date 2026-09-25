import { describe, it, expect, vi, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { ToastRoot } from 'reka-ui';
import { useToastStore } from '../../stores/toasts';
import ToastStack from './ToastStack.vue';

let wrapper;

async function mountStack(props = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);
    wrapper = mount(ToastStack, { props, global: { plugins: [pinia] }, attachTo: document.body });
    await flushPromises();

    return { wrapper, store: useToastStore() };
}

const toasts = () => [...document.querySelectorAll('[data-toast]')];

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe('ToastStack', () => {
    it('shows every notification of the store in a single stack, bottom right', async () => {
        const { store } = await mountStack();

        store.show({ title: 'Profil mis à jour', tone: 'success' });
        store.show({ title: 'Favori ajouté' });
        await flushPromises();

        expect(toasts().map((toast) => toast.textContent)).toEqual([expect.stringContaining('Profil mis à jour'), expect.stringContaining('Favori ajouté')]);
        expect(document.querySelectorAll('[data-toast-viewport]')).toHaveLength(1);
        expect(document.querySelector('[data-toast-viewport]').className).toEqual(expect.stringContaining('bottom-4'));
    });

    it('leaves room for the floating tasks button when asked', async () => {
        await mountStack({ offset: 'above-fab' });

        expect(document.querySelector('[data-toast-viewport]').className).toContain('bottom-24');
    });

    // Reka announces each toast through a short-lived live region whose politeness follows the
    // toast type: background is polite, foreground assertive. The type is what this stack sets.
    it('speaks politely, and assertively for errors', async () => {
        const { wrapper, store } = await mountStack();

        store.show({ title: 'Enregistré', tone: 'success' });
        store.show({ title: 'Échec de la synchronisation', tone: 'error' });
        await flushPromises();

        expect(wrapper.findAllComponents(ToastRoot).map((toast) => toast.props('type'))).toEqual(['background', 'foreground']);
    });

    it('closes itself after five seconds, except for errors', async () => {
        vi.useFakeTimers();
        const { store } = await mountStack();
        store.show({ title: 'Enregistré', tone: 'success' });
        store.show({ title: 'Échec', tone: 'error' });
        await flushPromises();

        vi.advanceTimersByTime(4900);
        await flushPromises();
        expect(store.items).toHaveLength(2);

        vi.advanceTimersByTime(200);
        await flushPromises();
        expect(store.items.map((item) => item.title)).toEqual(['Échec']);
    });

    it('has a named close button', async () => {
        const { store } = await mountStack();
        store.show({ title: 'Échec', tone: 'error' });
        await flushPromises();

        document.querySelector('[data-toast] button[aria-label="Fermer la notification"]').click();
        await flushPromises();

        expect(store.items).toEqual([]);
    });

    it('never takes the focus away from what the visitor is doing', async () => {
        const { store } = await mountStack();
        const field = document.createElement('input');
        document.body.appendChild(field);
        field.focus();

        store.show({ title: 'Échec', tone: 'error' });
        await flushPromises();

        expect(document.activeElement).toBe(field);
    });

    it('shows the description under the title', async () => {
        const { store } = await mountStack();

        store.show({ title: 'Import terminé', description: '8 étapes, aucune ligne touchée.' });
        await flushPromises();

        expect(toasts()[0].textContent).toContain('8 étapes, aucune ligne touchée.');
    });

    it('offers the action of a notification as a link', async () => {
        const { store } = await mountStack();

        store.show({ title: 'Session expirée', tone: 'warning', action: { label: 'Se reconnecter', href: '/auth/blizzard/redirect' } });
        await flushPromises();

        const action = toasts()[0].querySelector('a[href="/auth/blizzard/redirect"]');

        expect(action.textContent.trim()).toBe('Se reconnecter');
    });

    it('keeps a notification with an action until it is closed', async () => {
        vi.useFakeTimers();
        const { store } = await mountStack();
        store.show({ title: 'Connexion requise', action: { label: 'Se connecter', href: '/auth/blizzard/redirect' } });
        await flushPromises();

        vi.advanceTimersByTime(10000);
        await flushPromises();

        expect(store.items).toHaveLength(1);
    });
});
