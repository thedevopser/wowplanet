// @vitest-environment node
import { describe, it, expect, vi } from 'vitest';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Dialog from './Dialog.vue';
import Combobox from './Combobox.vue';
import Select from './Select.vue';
import Drawer from './Drawer.vue';
import DropdownMenu from './DropdownMenu.vue';
import Tabs from './Tabs.vue';

const render = (component, props, slots) => renderToString(createSSRApp({ render: () => h(component, props, slots) }));

describe('server-side rendering of the interactive primitives', () => {
    it('runs without any browser global', () => {
        expect(typeof window).toBe('undefined');
        expect(typeof document).toBe('undefined');
    });

    it('renders the tabs with the chosen panel in the HTML', async () => {
        const html = await render(
            Tabs,
            { tabs: [{ value: 'a', label: 'Aperçu' }, { value: 'b', label: 'Collections' }], label: 'Sections', modelValue: 'b' },
            { a: () => 'Contenu A', b: () => 'Contenu B' },
        );

        expect(html).toContain('role="tablist"');
        expect(html).toContain('Collections');
        expect(html).toContain('Contenu B');
    });

    it('renders a closed dialog as its trigger only', async () => {
        const html = await render(Dialog, { title: 'Partager' }, { trigger: () => h('button', 'Partager ce score') });

        expect(html).toContain('Partager ce score');
        expect(html).not.toContain('role="dialog"');
    });

    it('renders a closed drawer as its trigger only', async () => {
        const html = await render(Drawer, { title: 'Menu' }, { trigger: () => h('button', 'Ouvrir le menu') });

        expect(html).toContain('Ouvrir le menu');
        expect(html).not.toContain('role="dialog"');
    });

    it('renders a closed select as its trigger, showing the chosen option', async () => {
        const html = await render(Select, { label: 'Extension', options: [{ value: '11', label: 'Midnight' }], modelValue: '11' });

        expect(html).toContain('Extension');
        expect(html).not.toContain('role="listbox"');
    });

    it('renders a closed combobox as its labelled field, holding the current value', async () => {
        const html = await render(Combobox, { label: 'Catégorie', options: ['Legion'], modelValue: 'Legion' });

        expect(html).toContain('Catégorie');
        expect(html).toContain('value="Legion"');
        expect(html).not.toContain('role="listbox"');
    });

    it('renders a closed menu as its trigger only', async () => {
        const html = await render(
            DropdownMenu,
            { items: [{ key: 'a', label: 'Mon compte' }], label: 'Compte' },
            { trigger: () => h('button', 'Arthas') },
        );

        expect(html).toContain('Arthas');
        expect(html).not.toContain('role="menu"');
    });
});
