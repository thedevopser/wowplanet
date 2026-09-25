import { describe, it, expect, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { InvalidToastError, useToastStore } from './toasts';

beforeEach(() => {
    setActivePinia(createPinia());
});

describe('toasts store', () => {
    it('stacks a notification with an informative tone by default', () => {
        const store = useToastStore();

        store.show({ title: 'Profil mis à jour' });

        expect(store.items).toEqual([{ id: 1, title: 'Profil mis à jour', description: '', tone: 'info' }]);
    });

    it('gives each notification its own identifier', () => {
        const store = useToastStore();

        const first = store.show({ title: 'Un' });
        const second = store.show({ title: 'Deux', description: 'Détail', tone: 'success' });

        expect(first).not.toBe(second);
        expect(store.items.map((item) => item.id)).toEqual([first, second]);
    });

    it('removes a dismissed notification only', () => {
        const store = useToastStore();
        const first = store.show({ title: 'Un' });
        store.show({ title: 'Deux' });

        store.dismiss(first);

        expect(store.items.map((item) => item.title)).toEqual(['Deux']);
    });

    it('keeps the action offered by a notification', () => {
        const store = useToastStore();

        store.show({ title: 'Session expirée', tone: 'warning', action: { label: 'Se reconnecter', href: '/auth/blizzard/redirect' } });

        expect(store.items[0].action).toEqual({ label: 'Se reconnecter', href: '/auth/blizzard/redirect' });
    });

    it('stores no action when none is offered', () => {
        const store = useToastStore();

        store.show({ title: 'Profil mis à jour' });

        expect(store.items[0].action).toBeUndefined();
    });

    it.each([
        [{ title: 'Ok', action: { label: 'Aller' } }],
        [{ title: 'Ok', action: { href: '/faq' } }],
        [{ title: 'Ok', action: { label: '', href: '/faq' } }],
        [{ title: 'Ok', action: 'Aller' }],
        [{}],
        [{ title: '' }],
        [{ title: 42 }],
        [{ title: 'Ok', tone: 'gold' }],
        [{ title: 'Ok', description: 3 }],
    ])('refuses %j', (toast) => {
        expect(() => useToastStore().show(toast)).toThrow(InvalidToastError);
        expect(useToastStore().items).toEqual([]);
    });
});
