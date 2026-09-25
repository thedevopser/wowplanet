import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/character/hyjal/arthas/progression/quetes', props: {} });

    return { __page: page, usePage: () => page, router: { replace: vi.fn() } };
});

import { __page, router } from '@inertiajs/vue3';
import { mountWithPlugins } from '../tests/helpers';
import QuestsTab from './QuestsTab.vue';

const characterData = {
    name: 'Arthas',
    collections: {
        11: {
            quests: {
                completed: 25,
                total: 50,
                zones: [
                    { name: 'Isle of Dorn', completed: 1, total: 1, items: [{ id: 3, name: 'Quête C', is_completed: true }] },
                    { name: 'Dornogal', completed: 1, total: 3, items: [
                        { id: 2, name: 'Quête B', is_completed: false },
                        { id: 1, name: 'Quête A', is_completed: true },
                        { id: 4, name: 'Quête D', is_completed: false },
                    ] },
                ],
            },
        },
        0: { quests: { completed: 500, total: 1000, zones: [] } },
    },
};

const crossCharacter = { completedQuestIds: [4], questOwners: { 4: 'Jaina' } };

const mountTab = (state = {}) => mountWithPlugins(QuestsTab, {
    initialState: { character: { character: characterData, crossCharacter: null, ...state } },
    stubActions: false,
});

const groupNames = (wrapper) => wrapper.findAll('[data-group-name]').map((name) => name.text());

beforeEach(() => {
    __page.url = '/character/hyjal/arthas/progression/quetes';
    router.replace.mockClear();
});

describe('QuestsTab', () => {
    it('titles the sub-tab and sums up the expansion', async () => {
        const wrapper = await mountTab();

        expect(wrapper.find('h2').text()).toBe('Quêtes');
        expect(wrapper.find('[data-percent]').text()).toBe('50 %');
        expect(wrapper.text()).toContain('25 / 50');
    });

    it('opens on the latest expansion', async () => {
        const wrapper = await mountTab();

        expect(wrapper.findComponent({ name: 'Select' }).props('modelValue')).toBe('11');
    });

    it('reads the expansion from the address', async () => {
        __page.url = '/character/hyjal/arthas/progression/quetes?extension=0';

        const wrapper = await mountTab();

        expect(wrapper.find('[data-percent]').text()).toBe('50 %');
        expect(wrapper.text()).toContain('500 / 1000');
    });

    it('writes a chosen expansion in the address', async () => {
        const wrapper = await mountTab();

        await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', '0');

        expect(router.replace.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas/progression/quetes?extension=0');
    });

    it('sorts the zones by name', async () => {
        const wrapper = await mountTab();

        expect(groupNames(wrapper)).toEqual(['Dornogal', 'Isle of Dorn']);
    });

    it('lists the quests of an expanded zone by name, linked to Wowhead', async () => {
        const wrapper = await mountTab();

        await wrapper.find('[data-group] > button').trigger('click');

        const links = wrapper.findAll('[data-group] li a');
        expect(links.map((link) => link.text())).toEqual(['Quête A', 'Quête B', 'Quête D']);
        expect(links[0].attributes('href')).toBe('https://www.wowhead.com/fr/quest=1');
    });

    it('says what is done, done elsewhere and left to do', async () => {
        const wrapper = await mountTab({ crossCharacter });

        await wrapper.find('[data-group] > button').trigger('click');

        const marks = wrapper.findAll('[data-group] li').map((row) => row.findComponent({ name: 'CompletionMark' }).text());
        expect(marks).toEqual(['Fait', 'À faire', 'Fait par Jaina']);
    });

    it('filters the quests by name', async () => {
        const wrapper = await mountTab();

        await wrapper.find('input[type="search"]').setValue('quête c');

        expect(groupNames(wrapper)).toEqual(['Isle of Dorn']);
    });

    it('hides the completed quests on demand', async () => {
        const wrapper = await mountTab();

        await wrapper.findAll('button').find((button) => button.text().includes('Masquer')).trigger('click');

        expect(groupNames(wrapper)).toEqual(['Dornogal']);
        expect(wrapper.find('[data-group]').text()).toContain('0 / 2');
    });

    it('explains an empty search', async () => {
        const wrapper = await mountTab();

        await wrapper.find('input[type="search"]').setValue('introuvable');

        expect(wrapper.text()).toContain('Aucune quête ne correspond à ces filtres.');
    });

    it('explains an expansion without data', async () => {
        __page.url = '/character/hyjal/arthas/progression/quetes?extension=5';

        const wrapper = await mountTab();

        expect(wrapper.text()).toContain('Aucune quête connue pour cette extension.');
    });
});
