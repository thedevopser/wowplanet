import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees/montures', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import DatabaseMountsPage from './DatabaseMountsPage.vue';
import { mountWithPlugins } from '../tests/helpers';
import { expectNoAxeViolations } from '../tests/axe';

const meta = {
    title: 'Montures WoW | WowPlanet',
    description: 'Montures',
    ogTitle: 'Montures', ogDescription: 'Montures', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/montures', jsonLd: null,
};

const items = [
    { id: 1, name_fr: 'Destrier noir', source: 'Vendeur', icon_url: '', source_spell_id: 100 },
    { id: 2, name_fr: 'Loup rapide', source: 'Vendeur', icon_url: '', source_spell_id: 101 },
    { id: 3, name_fr: 'Dragon rouge', source: 'Raid', icon_url: '', source_spell_id: 102 },
];

const categories = [
    { slug: 'flying', name: 'Volantes', count: 50 },
    { slug: 'ground', name: 'Terrestres', count: 80 },
];

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseMountsPage, {
        props: { meta, category: null, items, categories, total: 942, ...props },
        stubs: { SearchFilter: true, CollectionIcon: true },
    });
}

describe('DatabaseMountsPage', () => {
    it('renders heading "Montures" and total count', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('942');
    });

    it('displays items from props', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Destrier noir');
        expect(wrapper.text()).toContain('Raid');
    });

    it('shows category item count (not global total) when a category is active', async () => {
        const wrapper = await mountPage({ category: 'flying' });
        expect(wrapper.text()).toContain('3');
    });

    it('search filters items by name', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Destrier noir');

        wrapper.vm.search = 'dragon';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Dragon rouge');
        expect(wrapper.text()).not.toContain('Destrier noir');
    });

    it('shows empty state when no items', async () => {
        const wrapper = await mountPage({ items: [], total: 0 });
        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });

    it('lays the catalogue out in a captioned table, without a dead loading line', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('table caption').text()).toBe('Liste des montures');
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
        expect(pagination.props()).toEqual(expect.objectContaining({ currentPage: 1, lastPage: 3, total: 120, label: 'Pages des montures' }));

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

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage();

        await expectNoAxeViolations(wrapper.element);
    });
});
