import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/character/hyjal/arthas/collections/garde-robe', props: {} });

    return { __page: page, usePage: () => page, router: { replace: vi.fn() } };
});

import { __page, router } from '@inertiajs/vue3';
import { mountWithPlugins } from '../tests/helpers';
import TransmogTab from './TransmogTab.vue';

const APPEARANCES = [
    { slot: 'HEAD', category: 'Armure', total: 10, completed: 5 },
    { slot: 'CHEST', category: 'Armure', total: 4, completed: 1 },
    { slot: 'WEAPON', category: 'Arme', total: 6, completed: 0 },
];

const mountTab = (appearances = APPEARANCES) => mountWithPlugins(TransmogTab, { props: { character: { appearances } } });

const slotNames = (wrapper) => wrapper.findAll('[data-slot] [data-slot-name]').map((name) => name.text());

beforeEach(() => {
    __page.url = '/character/hyjal/arthas/collections/garde-robe';
    router.replace.mockClear();
});

describe('TransmogTab', () => {
    it('titles the sub-tab and sums up every appearance', async () => {
        const wrapper = await mountTab();

        expect(wrapper.find('h2').text()).toBe('Garde-robe');
        expect(wrapper.find('[data-percent]').text()).toBe('30 %');
        expect(wrapper.text()).toContain('6 / 20');
    });

    it('lists the slots by translated name, each with a labelled bar', async () => {
        const wrapper = await mountTab();

        expect(slotNames(wrapper)).toEqual(['Arme', 'Tête', 'Torse']);
        expect(wrapper.find('[data-slot] [role="progressbar"]').attributes('aria-label')).toBe('Arme : 0 sur 6');
    });

    it('offers the categories in a labelled list, all of them by default', async () => {
        const wrapper = await mountTab();
        const select = wrapper.findComponent({ name: 'Select' });

        expect(select.props('label')).toBe('Catégorie');
        expect(select.props('options').map((option) => option.label)).toEqual(['Tout', 'Arme', 'Armure']);
        expect(select.props('modelValue')).toBe('tout');
    });

    it('shows one category, read from the address', async () => {
        __page.url = '/character/hyjal/arthas/collections/garde-robe?categorie=Armure';

        const wrapper = await mountTab();

        expect(slotNames(wrapper)).toEqual(['Tête', 'Torse']);
    });

    it('writes a chosen category in the address', async () => {
        const wrapper = await mountTab();

        await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'Arme');

        expect(router.replace.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas/collections/garde-robe?categorie=Arme');
    });

    it('explains an empty wardrobe', async () => {
        const wrapper = await mountTab([]);

        expect(wrapper.text()).toContain('Aucune apparence connue pour ce personnage.');
    });
});
