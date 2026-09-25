import { describe, it, expect, vi, beforeEach } from 'vitest';

let pageUrl = '/base-de-donnees/professions';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: pageUrl, props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import DatabaseProfessionsPage from './DatabaseProfessionsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Professions WoW | WowPlanet',
    description: 'Professions',
    ogTitle: 'Professions', ogDescription: 'Professions', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/professions', jsonLd: null,
};

const professions = [
    { id: 1, name_fr: 'Alchimie', type: 'primary', slug: 'alchimie', recipe_count: 200 },
    { id: 2, name_fr: 'Cuisine', type: 'secondary', slug: 'cuisine', recipe_count: 80 },
];

const recipes = {
    items: [
        { id: 1, name_fr: 'Potion de soin', category_name: 'Potions', faction: null, wowhead_spell_id: 500 },
        { id: 2, name_fr: 'Élixir de force', category_name: 'Élixirs', faction: 'Alliance', wowhead_spell_id: 501 },
    ],
    expansions: [{ id: 10, name: 'The War Within', slug: 'the-war-within', count: 40 }],
    profession: { id: 1, name_fr: 'Alchimie', type: 'primary' },
    total: 2,
    current_page: 1,
    last_page: 3,
};

function mountList() {
    pageUrl = '/base-de-donnees/professions';
    return mountWithPlugins(DatabaseProfessionsPage, {
        props: { meta, profession: null, expansion: null, search: null, professions, total_recipes: 280, recipes: null },
        stubs: { SearchFilter: true },
    });
}

function mountDetail(props = {}) {
    pageUrl = '/base-de-donnees/professions/alchimie';
    return mountWithPlugins(DatabaseProfessionsPage, {
        props: { meta, profession: 'alchimie', expansion: null, search: null, professions, total_recipes: 280, recipes, ...props },
        stubs: { SearchFilter: true },
    });
}

describe('DatabaseProfessionsPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists primary and secondary professions with links', async () => {
        const wrapper = await mountList();
        expect(wrapper.text()).toContain('Professions principales');
        expect(wrapper.text()).toContain('Alchimie');
        expect(wrapper.text()).toContain('Professions secondaires');
        expect(wrapper.text()).toContain('Cuisine');

        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));
        expect(hrefs).toContain('/base-de-donnees/professions/alchimie');
        expect(hrefs).toContain('/base-de-donnees/professions/cuisine');
    });

    it('renders recipes in detail mode', async () => {
        const wrapper = await mountDetail();
        expect(wrapper.text()).toContain('Potion de soin');
        expect(wrapper.text()).toContain('Élixir de force');
        expect(wrapper.text()).toContain('The War Within');
    });

    it('toggles expansion via a partial Inertia visit', async () => {
        const wrapper = await mountDetail();
        const expansion = wrapper.find('button[data-expansion="the-war-within"]');

        expect(expansion.attributes('aria-pressed')).toBe('false');
        await expansion.trigger('click');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/professions/alchimie',
            expect.objectContaining({ expansion: 'the-war-within', page: 1 }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('paginates recipes via a partial Inertia visit', async () => {
        const wrapper = await mountDetail();
        await wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 2);

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/professions/alchimie',
            expect.objectContaining({ page: 2 }),
            expect.objectContaining({ only: expect.arrayContaining(['recipes']) }),
        );
    });

    it('searches recipes via a partial Inertia visit', async () => {
        const wrapper = await mountDetail();
        await wrapper.findComponent({ name: 'SearchFilter' }).vm.$emit('search-debounced', 'potion');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/professions/alchimie',
            expect.objectContaining({ page: 1, search: 'potion' }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('keeps the chosen expansion while paging, and marks it pressed', async () => {
        const wrapper = await mountDetail({ expansion: 'the-war-within' });

        expect(wrapper.find('button[data-expansion="the-war-within"]').attributes('aria-pressed')).toBe('true');
        await wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 2);

        expect(router.get.mock.calls.at(-1)[1]).toEqual(expect.objectContaining({ expansion: 'the-war-within', page: 2 }));
    });

    it('lets a pressed expansion go back to all of them', async () => {
        const wrapper = await mountDetail({ expansion: 'the-war-within' });

        await wrapper.find('button[data-expansion="the-war-within"]').trigger('click');

        expect(router.get.mock.calls.at(-1)[1]).toEqual(expect.objectContaining({ expansion: undefined, page: 1 }));
    });

    it('titles the recipes of the profession and lays them out in a captioned table', async () => {
        const wrapper = await mountDetail();

        expect(wrapper.find('h1').text()).toBe('Alchimie');
        expect(wrapper.find('table caption').text()).toBe('Recettes : Alchimie');
        expect(wrapper.find('[data-section-rule]').attributes('style')).toContain('border-left-color');
    });

    it('dims the recipes while another page loads', async () => {
        const wrapper = await mountDetail();

        await wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 2);
        router.get.mock.calls.at(-1)[2].onStart();
        await nextTick();

        expect(wrapper.find('table').attributes('aria-busy')).toBe('true');
    });

    it('explains a search that finds no recipe', async () => {
        const wrapper = await mountDetail({ recipes: { ...recipes, items: [], total: 0, last_page: 1 } });

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });

    it('opens each profession from a card in a list', async () => {
        const wrapper = await mountList();

        expect(wrapper.findAll('h2').map((heading) => heading.text())).toEqual(['Professions principales', 'Professions secondaires']);
        expect(wrapper.findAll('ul li a').map((link) => link.attributes('href'))).toEqual(
            ['/base-de-donnees/professions/alchimie', '/base-de-donnees/professions/cuisine'],
        );
    });

    it('offers the pagination above and below the table', async () => {
        const wrapper = await mountDetail();
        const paginations = wrapper.findAllComponents({ name: 'DatabasePagination' });

        expect(paginations.map((pagination) => pagination.props('placement'))).toEqual(['top', 'bottom']);
        expect(paginations[0].element.compareDocumentPosition(wrapper.find('table').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });
});
