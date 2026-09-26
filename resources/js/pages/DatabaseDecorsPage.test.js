import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees/decorations', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import DatabaseDecorsPage from './DatabaseDecorsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Décorations WoW | WowPlanet',
    description: 'Décorations',
    ogTitle: 'Décorations', ogDescription: 'Décorations', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/decorations', jsonLd: null,
};

const items = [
    { id: 1, name_fr: 'Table ronde', source: 'Artisanat', icon_url: '', item_id: 100 },
    { id: 2, name_fr: 'Chaise dorée', source: 'Vendeur', icon_url: '', item_id: 101 },
];

const categories = [{ slug: 'furniture', name: 'Mobilier', count: 40 }];

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseDecorsPage, {
        props: { meta, category: null, items, categories, total: 3200, ...props },
        stubs: { SearchFilter: true, CollectionIcon: true },
    });
}

describe('DatabaseDecorsPage', () => {
    it('renders heading and displays items from props', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Décorations');
        expect(wrapper.text()).toContain('Table ronde');
        expect(wrapper.text()).toContain('Chaise dorée');
    });

    it('search filters items by name', async () => {
        const wrapper = await mountPage();
        wrapper.vm.search = 'chaise';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Chaise dorée');
        expect(wrapper.text()).not.toContain('Table ronde');
    });

    it('shows empty state when no items', async () => {
        const wrapper = await mountPage({ items: [], total: 0 });
        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });

    it('lays the catalogue out in a captioned table, without a dead loading line', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('table caption').text()).toBe('Liste des décorations');
        expect(wrapper.text()).not.toContain('Chargement');
    });

    it('edges its header with the colour of its section', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-section-rule]').attributes('style')).toContain('border-left-color');
    });

    const manyItems = Array.from({ length: 120 }, (_, index) => ({ id: index + 1, name_fr: `Objet ${index + 1}`, source: 'Vendeur', icon_url: '' }));

    it('shows the list fifty rows at a time', async () => {
        const wrapper = await mountPage({ items: manyItems, total: 120 });
        const pagination = wrapper.findComponent({ name: 'DatabasePagination' });

        expect(wrapper.findAll('tbody tr')).toHaveLength(50);
        expect(pagination.props()).toEqual(expect.objectContaining({ currentPage: 1, lastPage: 3, total: 120, label: 'Pages des décorations' }));

        await pagination.vm.$emit('page-change', 3);

        expect(wrapper.findAll('tbody tr')).toHaveLength(20);
        expect(wrapper.find('tbody').text()).toContain('Objet 101');
    });

    it('goes back to the first page for a new search', async () => {
        const wrapper = await mountPage({ items: manyItems, total: 120 });

        await wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 3);
        await wrapper.findComponent({ name: 'SearchFilter' }).vm.$emit('update:search', 'Objet');

        expect(wrapper.findComponent({ name: 'DatabasePagination' }).props('currentPage')).toBe(1);
    });

    it('offers the pagination above and below the table', async () => {
        const wrapper = await mountPage({ items: manyItems, total: 120 });
        const paginations = wrapper.findAllComponents({ name: 'DatabasePagination' });

        expect(paginations.map((pagination) => pagination.props('placement'))).toEqual(['top', 'bottom']);
        expect(paginations[0].element.compareDocumentPosition(wrapper.find('table').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('shows the source in French', async () => {
        const wrapper = await mountPage({ items: [{ id: 9, name_fr: 'Objet', source: 'Neighbourhood Vendor', icon_url: '' }] });

        expect(wrapper.find('tbody').text()).toContain('Vendeur de quartier');
        expect(wrapper.find('tbody').text()).not.toContain('Neighbourhood Vendor');
    });

    it('names an unknown source as such', async () => {
        const wrapper = await mountPage({ items: [{ id: 9, name_fr: 'Objet', source: null, icon_url: '' }] });

        expect(wrapper.find('tbody').text()).toContain('Inconnu');
    });

    it('names the active category in French', async () => {
        const wrapper = await mountPage({ category: 'neighbourhoods', categories: [{ slug: 'neighbourhoods', name: 'Neighbourhoods', count: 1 }] });

        expect(wrapper.find('header').text()).toContain('Quartiers');
    });
});
