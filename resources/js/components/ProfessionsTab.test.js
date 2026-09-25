import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/character/hyjal/arthas/progression/metiers', props: {} });

    return { __page: page, usePage: () => page, router: { replace: vi.fn() } };
});

import { __page, router } from '@inertiajs/vue3';
import ProfessionsTab from './ProfessionsTab.vue';
import { mountWithPlugins } from '../tests/helpers';

const makeCharacter = (professions = []) => ({ professions });

const standardProfession = {
    profession_id: 164,
    profession_name: 'Forge',
    type: 'primary',
    is_archaeology: false,
    expansions: {
        11: {
            has_tier: true,
            tier_exists: true,
            completed: 5,
            total: 20,
            skill_points: 80,
            max_skill_points: 100,
            categories: [
                {
                    name: 'Armes',
                    completed: 2,
                    total: 5,
                    items: [
                        { id: 1, name: 'Épée en acier', is_completed: true, wowhead_spell_id: 9001 },
                        { id: 2, name: 'Bouclier lourd', is_completed: false, wowhead_spell_id: null },
                        { id: 3, name: 'Dague rapide', is_completed: true, wowhead_spell_id: 9003 },
                    ],
                },
                {
                    name: 'Armures',
                    completed: 3,
                    total: 8,
                    items: [
                        { id: 4, name: 'Plastron', is_completed: true, wowhead_spell_id: 9004 },
                    ],
                },
            ],
        },
        0: {
            has_tier: false,
            tier_exists: true,
            completed: 0,
            total: 10,
            skill_points: 0,
            max_skill_points: 300,
            categories: [],
        },
    },
};

const archaeologyProfession = {
    profession_id: 794,
    profession_name: 'Archéologie',
    type: 'secondary',
    is_archaeology: true,
    global_skill_points: 525,
    global_max_skill_points: 950,
    expansions: {
        0: { skill_points: 300, max_skill_points: 300 },
        11: { skill_points: 0, max_skill_points: 0 },
    },
};

const secondProfession = {
    profession_id: 202,
    profession_name: 'Ingénierie',
    type: 'primary',
    is_archaeology: false,
    expansions: {
        11: {
            has_tier: true,
            tier_exists: true,
            completed: 1,
            total: 5,
            skill_points: 10,
            max_skill_points: 100,
            categories: [
                { name: 'Gadgets', completed: 1, total: 5, items: [{ id: 10, name: 'Bombe', is_completed: true, wowhead_spell_id: 5000 }] },
            ],
        },
    },
};

const mountComponent = (professions = [], state = {}) =>
    mountWithPlugins(ProfessionsTab, {
        initialState: { character: { character: makeCharacter(professions), crossCharacter: null, ...state } },
        stubActions: false,
    });

const professionSelect = (wrapper) => wrapper.findAllComponents({ name: 'Select' }).find((select) => select.props('label') === 'Métier');
const groupNames = (wrapper) => wrapper.findAll('[data-group-name]').map((name) => name.text());

beforeEach(() => {
    __page.url = '/character/hyjal/arthas/progression/metiers';
    router.replace.mockClear();
});

describe('ProfessionsTab', () => {
    it('explains a character without profession', async () => {
        const wrapper = await mountComponent([]);

        expect(wrapper.text()).toContain('Ce personnage n’a aucun métier.');
    });

    it('offers the professions in a labelled list, secondary ones marked', async () => {
        const wrapper = await mountComponent([standardProfession, archaeologyProfession]);

        expect(professionSelect(wrapper).props('options')).toEqual([
            { value: '164', label: 'Forge' },
            { value: '794', label: 'Archéologie', hint: 'Secondaire' },
        ]);
    });

    it('opens on the first profession and the latest expansion', async () => {
        const wrapper = await mountComponent([standardProfession, secondProfession]);

        expect(professionSelect(wrapper).props('modelValue')).toBe('164');
        expect(wrapper.find('h2').text()).toBe('Forge');
    });

    it('reads the profession from the address, and writes a new one there', async () => {
        __page.url = '/character/hyjal/arthas/progression/metiers?metier=202';
        const wrapper = await mountComponent([standardProfession, secondProfession]);

        expect(wrapper.find('h2').text()).toBe('Ingénierie');

        await professionSelect(wrapper).vm.$emit('update:modelValue', '164');

        expect(router.replace.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas/progression/metiers');
    });

    it('sums up the recipes learned and the skill of the expansion', async () => {
        const wrapper = await mountComponent([standardProfession]);

        expect(wrapper.find('[data-percent]').text()).toBe('25 %');
        expect(wrapper.text()).toContain('5 / 20');
        expect(wrapper.find('[data-skill]').text()).toContain('80 / 100');
    });

    it('names a character with a better skill', async () => {
        const wrapper = await mountComponent([standardProfession], {
            crossCharacter: { skillPointOwners: { 164: { 11: { character_name: 'Jaina', skill_points: 95, max_skill_points: 100 } } } },
        });

        expect(wrapper.text()).toContain('Meilleur : Jaina — 95 / 100');
    });

    it('sorts the categories by name', async () => {
        const wrapper = await mountComponent([standardProfession]);

        expect(groupNames(wrapper)).toEqual(['Armes', 'Armures']);
    });

    it('explains an expansion whose tier is not learned', async () => {
        __page.url = '/character/hyjal/arthas/progression/metiers?extension=0';
        const wrapper = await mountComponent([standardProfession]);

        expect(wrapper.text()).toContain('Forge n’a pas été appris pour cette extension.');
    });

    it('shows the global skill of archaeology, without expansion', async () => {
        __page.url = '/character/hyjal/arthas/progression/metiers?metier=794';
        const wrapper = await mountComponent([standardProfession, archaeologyProfession]);

        expect(wrapper.find('[data-percent]').text()).toBe('55 %');
        expect(wrapper.text()).toContain('525 / 950');
        expect(wrapper.findComponent({ name: 'ExpansionFilter' }).exists()).toBe(false);
    });

    it('filters the recipes by name', async () => {
        const wrapper = await mountComponent([standardProfession]);

        await wrapper.find('input[type="search"]').setValue('plastron');

        expect(groupNames(wrapper)).toEqual(['Armures']);
    });

    it('explains an empty search', async () => {
        const wrapper = await mountComponent([standardProfession]);

        await wrapper.find('input[type="search"]').setValue('introuvable');

        expect(wrapper.text()).toContain('Aucune recette ne correspond à ces filtres.');
    });

    it('hides the learned recipes on demand', async () => {
        const wrapper = await mountComponent([standardProfession]);

        await wrapper.findAll('button').find((button) => button.text().includes('Masquer les recettes apprises')).trigger('click');

        expect(groupNames(wrapper)).toEqual(['Armes']);
        expect(wrapper.find('[data-group]').text()).toContain('0 / 1');
    });

    it('links a recipe to its spell, or to a search without one, and marks it', async () => {
        const wrapper = await mountComponent([standardProfession], {
            crossCharacter: { completedRecipeIds: [2], recipeOwners: { 2: 'Jaina' } },
        });

        await wrapper.find('[data-group] > button').trigger('click');

        const rows = wrapper.findAll('[data-group] li');
        const hrefs = rows.map((row) => row.find('a').attributes('href'));

        expect(hrefs).toEqual([
            'https://www.wowhead.com/fr/search?q=Bouclier%20lourd',
            'https://www.wowhead.com/fr/spell=9003',
            'https://www.wowhead.com/fr/spell=9001',
        ]);
        expect(rows[0].text()).toContain('Fait par Jaina');
    });
});
