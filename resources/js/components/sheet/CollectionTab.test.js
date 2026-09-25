import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/character/hyjal/arthas/collections/montures', props: {} });

    return { __page: page, usePage: () => page, router: { replace: vi.fn() } };
});

import { __page, router } from '@inertiajs/vue3';
import { mountWithPlugins } from '../../tests/helpers';
import CollectionTab from './CollectionTab.vue';

const mount = (id, category, source, done = false, extra = {}) => ({ id, name: `Monture ${id}`, category, source, is_completed: done, wowhead_id: 1000 + id, icon_url: null, ...extra });

const MOUNTS = [
    mount(1, 'The War Within', 'Raid Drop', true),
    mount(2, 'The War Within', 'Raid Drop'),
    mount(3, 'The War Within', 'Achievement', true, { name: 'Aigle' }),
    mount(4, 'World Events', 'Vendor'),
    mount(5, null, null, true, { name: 'Zébu' }),
];

const mountTab = (kind = 'mounts', items = MOUNTS) => mountWithPlugins(CollectionTab, {
    props: { kind, character: { [kind]: items } },
    stubActions: false,
});

const categorySelect = (wrapper) => wrapper.findComponent({ name: 'Select' });
const sourceNames = (wrapper) => wrapper.findAll('[data-group-name]').map((name) => name.text());

beforeEach(() => {
    __page.url = '/character/hyjal/arthas/collections/montures';
    router.replace.mockClear();
});

describe('CollectionTab', () => {
    it('offers the categories in a labelled list, translated, each with its progress', async () => {
        const wrapper = await mountTab();

        expect(categorySelect(wrapper).props('label')).toBe('Catégorie');
        expect(categorySelect(wrapper).props('options').map((option) => [option.value, option.label, option.hint])).toEqual([
            ['The War Within', 'The War Within', '2 / 3'],
            ['World Events', 'Événements mondiaux', '0 / 1'],
            ['Non classé', 'Non classé', '1 / 1'],
        ]);
    });

    it('opens on the first category and sums it up', async () => {
        const wrapper = await mountTab();

        expect(wrapper.find('h2').text()).toBe('Montures');
        expect(wrapper.find('[data-percent]').text()).toBe('67 %');
    });

    it('reads the category from the address and writes a new one there', async () => {
        __page.url = '/character/hyjal/arthas/collections/montures?categorie=World%20Events';
        const wrapper = await mountTab();

        expect(sourceNames(wrapper)).toEqual(['Vendeur']);

        await categorySelect(wrapper).vm.$emit('update:modelValue', 'The War Within');

        expect(router.replace.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas/collections/montures');
    });

    it('groups the category by source, translated and sorted', async () => {
        const wrapper = await mountTab();

        expect(sourceNames(wrapper)).toEqual(['Butin de raid', 'Haut-fait']);
    });

    it('lists the items of a source with their icon, their Wowhead link and their state', async () => {
        const wrapper = await mountTab();

        await wrapper.find('[data-group] > button').trigger('click');

        const rows = wrapper.findAll('[data-group] li');
        expect(rows.map((row) => row.find('a').text())).toEqual(['Monture 1', 'Monture 2']);
        expect(rows[0].find('a').attributes('href')).toBe('https://www.wowhead.com/fr/spell=1001');
        expect(rows.map((row) => row.findComponent({ name: 'CompletionMark' }).text())).toEqual(['Fait', 'À faire']);
    });

    it('lists the uncategorised items flat', async () => {
        __page.url = '/character/hyjal/arthas/collections/montures?categorie=Non%20class%C3%A9';
        const wrapper = await mountTab();

        expect(wrapper.find('h3').text()).toBe('Montures non classées');
        expect(wrapper.findAll('[data-loose-item] a').map((link) => link.text())).toEqual(['Zébu']);
    });

    it('searches and hides the owned items', async () => {
        const wrapper = await mountTab();

        await wrapper.find('input[type="search"]').setValue('aigle');
        expect(sourceNames(wrapper)).toEqual(['Haut-fait']);

        await wrapper.find('input[type="search"]').setValue('');
        await wrapper.findAll('button').find((button) => button.text().includes('Masquer')).trigger('click');
        expect(sourceNames(wrapper)).toEqual(['Butin de raid']);
    });

    it.each([
        ['pets', 'Mascottes', 'https://www.wowhead.com/fr/npc=1001'],
        ['decor', 'Décorations', 'https://www.wowhead.com/fr/item=77'],
    ])('serves the %s collection the same way', async (kind, title, href) => {
        const items = [{ id: 1, name: 'Objet', category: 'Classic', source: 'Vendor', is_completed: false, wowhead_id: 1001, item_id: 77 }];
        const wrapper = await mountTab(kind, items);

        await wrapper.find('[data-group] > button').trigger('click');

        expect(wrapper.find('h2').text()).toBe(title);
        expect(wrapper.find('[data-group] li a').attributes('href')).toBe(href);
    });

    it('explains an empty collection', async () => {
        const wrapper = await mountTab('mounts', []);

        expect(wrapper.text()).toContain('Aucune monture connue pour ce personnage.');
    });
});
