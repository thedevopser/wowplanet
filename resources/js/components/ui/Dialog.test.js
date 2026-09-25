import { describe, it, expect, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Dialog from './Dialog.vue';

let wrapper;

async function mountDialog(props = {}, slots = {}) {
    wrapper = mount(Dialog, {
        props: { title: 'Partager ce score', ...props },
        slots: {
            trigger: '<button type="button" data-trigger>Partager</button>',
            default: '<p>Contenu du partage</p><input data-field aria-label="Lien">',
            ...slots,
        },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

async function open() {
    document.querySelector('[data-trigger]').click();
    await flushPromises();
}

const dialog = () => document.querySelector('[role="dialog"]');

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('Dialog', () => {
    it('stays closed until its trigger is used', async () => {
        await mountDialog();

        expect(dialog()).toBeNull();

        await open();

        expect(dialog()).not.toBeNull();
    });

    it('is named by its title', async () => {
        await mountDialog();
        await open();

        const titleId = dialog().getAttribute('aria-labelledby');

        expect(document.getElementById(titleId).textContent).toBe('Partager ce score');
    });

    it('is described by its description when given one', async () => {
        await mountDialog({ description: 'Copiez le lien ou téléchargez l’image.' });
        await open();

        const descriptionId = dialog().getAttribute('aria-describedby');

        expect(document.getElementById(descriptionId).textContent).toBe('Copiez le lien ou téléchargez l’image.');
    });

    it('moves the focus inside itself once open', async () => {
        await mountDialog();
        await open();

        expect(dialog().contains(document.activeElement)).toBe(true);
    });

    it('closes on Escape', async () => {
        await mountDialog();
        await open();

        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(dialog()).toBeNull();
    });

    // Reka hands the focus back to the registered trigger on close. happy-dom loses that focus
    // again while the content unmounts, so the return itself is checked by hand (see US-07).
    it('registers its trigger as the element the focus returns to', async () => {
        await mountDialog();
        const trigger = document.querySelector('[data-trigger]');

        expect(trigger.getAttribute('aria-haspopup')).toBe('dialog');
        expect(trigger.getAttribute('aria-expanded')).toBe('false');

        await open();

        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(trigger.getAttribute('aria-controls')).toBe(dialog().id);
    });

    it('offers a named close button', async () => {
        await mountDialog();
        await open();

        const close = dialog().querySelector('button[aria-label="Fermer"]');
        close.click();
        await flushPromises();

        expect(dialog()).toBeNull();
    });

    it('dims the page behind it at 60 %', async () => {
        await mountDialog();
        await open();

        expect(document.querySelector('[data-dialog-overlay]').className).toContain('bg-night-950/60');
    });

    it('enters and leaves with the animations of the motion scale', async () => {
        await mountDialog();
        await open();

        expect(dialog().className).toContain('data-[state=open]:animate-dialog-in');
        expect(dialog().className).toContain('data-[state=closed]:animate-dialog-out');
    });

    it('keeps a reading width by default and widens for a wide content', async () => {
        await mountDialog({ open: true });
        expect(dialog().className).toContain('max-w-lg');
        wrapper.unmount();
        document.body.innerHTML = '';

        await mountDialog({ open: true, size: 'wide' });
        expect(dialog().className).toContain('max-w-3xl');
        expect(dialog().className).not.toContain('max-w-lg');
    });

    it('is driven from outside through its open state', async () => {
        await mountDialog({ open: true });

        expect(dialog()).not.toBeNull();

        await wrapper.setProps({ open: false });
        await flushPromises();

        expect(dialog()).toBeNull();
    });

    it('reports when the visitor closes it', async () => {
        await mountDialog({ open: true });

        dialog().querySelector('button[aria-label="Fermer"]').click();
        await flushPromises();

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('shows its footer actions when given', async () => {
        await mountDialog({ open: true }, { footer: '<button type="button">Copier</button>' });

        expect(dialog().querySelector('[data-dialog-footer] button').textContent).toBe('Copier');
    });

    it('can be opened by its state alone, without a trigger of its own', async () => {
        wrapper = mount(Dialog, { props: { title: 'Confirmer', open: true }, slots: { default: '<p>Sûr ?</p>' }, attachTo: document.body });
        await flushPromises();

        expect(dialog().textContent).toContain('Sûr ?');
        expect(document.querySelector('[data-trigger]')).toBeNull();
    });
});
