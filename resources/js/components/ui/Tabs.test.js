import { describe, it, expect, vi, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Tabs from './Tabs.vue';

const TABS = [
    { value: 'quests', label: 'Quêtes' },
    { value: 'achievements', label: 'Hauts-faits' },
    { value: 'reputations', label: 'Réputations' },
];

let wrapper;

async function mountTabs(props = {}) {
    wrapper = mount(Tabs, {
        props: { tabs: TABS, label: 'Progression', ...props },
        slots: {
            quests: '<p>Contenu quêtes</p>',
            achievements: '<p>Contenu hauts-faits</p>',
            reputations: '<p>Contenu réputations</p>',
        },
        attachTo: document.body,
    });
    // Reka registers panels and focusable tabs once mounted.
    await flushPromises();

    return wrapper;
}

const triggers = () => wrapper.findAll('[role="tab"]');
const selected = () => triggers().find((tab) => tab.attributes('aria-selected') === 'true');

afterEach(() => {
    wrapper?.unmount();
    vi.restoreAllMocks();
});

describe('Tabs', () => {
    it('names its tab list', async () => {
        const list = (await mountTabs()).find('[role="tablist"]');

        expect(list.attributes('aria-label')).toBe('Progression');
    });

    it('opens on the first tab by default', async () => {
        await mountTabs();

        expect(selected().text()).toBe('Quêtes');
        expect(wrapper.find('[role="tabpanel"]').text()).toBe('Contenu quêtes');
    });

    it('links each tab to its panel', async () => {
        await mountTabs();
        const tab = selected();
        const panel = wrapper.find('[role="tabpanel"]');

        expect(tab.attributes('aria-controls')).toBe(panel.attributes('id'));
        expect(panel.attributes('aria-labelledby')).toBe(tab.attributes('id'));
    });

    it('is driven from outside through its value, so that the address can choose the tab', async () => {
        await mountTabs({ modelValue: 'reputations' });

        expect(selected().text()).toBe('Réputations');

        await wrapper.setProps({ modelValue: 'achievements' });

        expect(selected().text()).toBe('Hauts-faits');
    });

    it('reports the tab chosen by the visitor', async () => {
        await mountTabs({ modelValue: 'quests' });

        await triggers()[1].trigger('mousedown', { button: 0 });

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['achievements']);
    });

    describe('keyboard', () => {
        async function press(key) {
            document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
            await flushPromises();
        }

        it('moves to the next and previous tab with the arrows', async () => {
            await mountTabs();
            triggers()[0].element.focus();

            await press('ArrowRight');
            expect(document.activeElement.textContent).toBe('Hauts-faits');
            expect(selected().text()).toBe('Hauts-faits');

            await press('ArrowLeft');
            expect(document.activeElement.textContent).toBe('Quêtes');
        });

        it('jumps to the last and first tab with End and Home', async () => {
            await mountTabs();
            triggers()[0].element.focus();

            await press('End');
            expect(document.activeElement.textContent).toBe('Réputations');

            await press('Home');
            expect(document.activeElement.textContent).toBe('Quêtes');
        });
    });

    it('lets the primary sections scroll inside their bar rather than widen the page', async () => {
        await mountTabs({ variant: 'primary' });

        expect(wrapper.find('[role="tablist"]').classes()).toContain('overflow-x-auto');
        expect(triggers()[0].classes()).toEqual(expect.arrayContaining(['px-2', 'sm:px-4']));
        expect(wrapper.find('[role="tablist"]').classes()).toContain('overflow-y-hidden');
    });

    it.each([
        ['primary', 'border-b-2'],
        ['secondary', 'rounded-full'],
    ])('draws the %s variant its own way', async (variant, marker) => {
        await mountTabs({ variant });

        expect(triggers()[0].classes()).toContain(marker);
    });

    it('shows keyboard focus on each tab', async () => {
        await mountTabs();

        expect(triggers()[0].classes()).toContain('focus-visible:outline-2');
    });

    describe('secondary variant overflow', () => {
        function overflow(list, { scrollWidth, clientWidth, scrollLeft }) {
            Object.defineProperty(list.element, 'scrollWidth', { value: scrollWidth, configurable: true });
            Object.defineProperty(list.element, 'clientWidth', { value: clientWidth, configurable: true });
            list.element.scrollLeft = scrollLeft;
        }

        it('scrolls sideways instead of wrapping', async () => {
            await mountTabs({ variant: 'secondary' });

            expect(wrapper.find('[role="tablist"]').classes()).toContain('overflow-x-auto');
        });

        it('fades its right edge while more tabs are hidden there', async () => {
            await mountTabs({ variant: 'secondary' });
            const list = wrapper.find('[role="tablist"]');

            overflow(list, { scrollWidth: 600, clientWidth: 300, scrollLeft: 0 });
            await list.trigger('scroll');

            expect(list.attributes('data-overflow')).toBe('true');
        });

        it('stops fading once scrolled to the end', async () => {
            await mountTabs({ variant: 'secondary' });
            const list = wrapper.find('[role="tablist"]');

            overflow(list, { scrollWidth: 600, clientWidth: 300, scrollLeft: 300 });
            await list.trigger('scroll');

            expect(list.attributes('data-overflow')).toBe('false');
        });
    });

    it('warns about a variant outside the list', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        await mountTabs({ variant: 'pills' });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "variant"');
    });
});
