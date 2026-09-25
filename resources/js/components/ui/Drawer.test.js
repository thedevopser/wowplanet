import { describe, it, expect, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Drawer from './Drawer.vue';

let wrapper;

async function mountDrawer(props = {}) {
    wrapper = mount(Drawer, {
        props: { title: 'Menu', ...props },
        slots: {
            trigger: '<button type="button" data-trigger>Ouvrir le menu</button>',
            default: '<a href="/faq" data-link>FAQ</a>',
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

const panel = () => document.querySelector('[role="dialog"]');

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('Drawer', () => {
    it('stays closed until its trigger is used', async () => {
        await mountDrawer();

        expect(panel()).toBeNull();

        await open();

        expect(panel().textContent).toContain('FAQ');
    });

    it('is named by its title', async () => {
        await mountDrawer();
        await open();

        expect(document.getElementById(panel().getAttribute('aria-labelledby')).textContent).toBe('Menu');
    });

    it('slides from the right edge by default', async () => {
        await mountDrawer();
        await open();

        expect(panel().className).toContain('right-0');
    });

    it('can slide from the left edge', async () => {
        await mountDrawer({ side: 'left' });
        await open();

        expect(panel().className).toContain('left-0');
    });

    it('offers a named close button', async () => {
        await mountDrawer();
        await open();

        const close = panel().querySelector('button[aria-label="Fermer le menu"]');
        close.click();
        await flushPromises();

        expect(panel()).toBeNull();
    });

    it('closes on Escape', async () => {
        await mountDrawer();
        await open();

        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(panel()).toBeNull();
    });

    it('can be driven from outside', async () => {
        await mountDrawer({ open: true });

        expect(panel()).not.toBeNull();

        await wrapper.setProps({ open: false });
        await flushPromises();

        expect(panel()).toBeNull();
    });

    it('rejects an unknown side', async () => {
        expect(Drawer.props.side.validator('top')).toBe(false);
    });
});
