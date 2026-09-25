import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees/quetes', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import DatabaseQuestsPage from './DatabaseQuestsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Quêtes WoW | WowPlanet',
    description: 'Quêtes',
    ogTitle: 'Quêtes', ogDescription: 'Quêtes', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/quetes', jsonLd: null,
};

const items = [
    { id: 1, name_fr: 'La grande chasse', zone_name: 'Vallée', faction: 'Alliance' },
    { id: 2, name_fr: 'Le trésor perdu', zone_name: 'Désert', faction: 'Horde' },
];

const expansions = [{ id: 10, name: 'The War Within', slug: 'the-war-within', count: 300 }];

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseQuestsPage, {
        props: {
            meta, expansion: null, search: null, items, expansions,
            total: 2, current_page: 1, last_page: 3, ...props,
        },
        stubs: { SearchFilter: true },
    });
}

describe('DatabaseQuestsPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders heading and items from props', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('La grande chasse');
        expect(wrapper.text()).toContain('Le trésor perdu');
    });

    it('triggers a partial Inertia visit on page change', async () => {
        const wrapper = await mountPage();
        await wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 2);

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/quetes',
            expect.objectContaining({ page: 2 }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('triggers a partial Inertia visit on debounced search', async () => {
        const wrapper = await mountPage();
        await wrapper.findComponent({ name: 'SearchFilter' }).vm.$emit('search-debounced', 'trésor');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/quetes',
            expect.objectContaining({ page: 1, search: 'trésor' }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows empty state when no items', async () => {
        const wrapper = await mountPage({ items: [], total: 0, last_page: 1 });
        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });

    it('lays the catalogue out in a captioned table, without a dead loading line', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('table caption').text()).toBe('Liste des quêtes');
        expect(wrapper.text()).not.toContain('Chargement');
    });

    it('edges its header with the colour of its section', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-section-rule]').attributes('style')).toContain('border-left-color');
    });

    it('dims the table while another page loads', async () => {
        const wrapper = await mountPage();

        await wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 2);
        router.get.mock.calls.at(-1)[2].onStart();
        await nextTick();

        expect(wrapper.find('table').attributes('aria-busy')).toBe('true');
    });

    it('offers the pagination above and below the table', async () => {
        const wrapper = await mountPage();
        const paginations = wrapper.findAllComponents({ name: 'DatabasePagination' });

        expect(paginations.map((pagination) => pagination.props('placement'))).toEqual(['top', 'bottom']);
        expect(paginations[0].element.compareDocumentPosition(wrapper.find('table').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });
});
