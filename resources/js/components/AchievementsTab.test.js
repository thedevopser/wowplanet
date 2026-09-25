import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/character/hyjal/arthas/progression/hauts-faits', props: {} });

    return { usePage: () => page, router: { replace: vi.fn() } };
});

import { mountWithPlugins } from '../tests/helpers';
import AchievementsTab from './AchievementsTab.vue';

const characterData = {
    collections: {
        11: {
            achievements: {
                completed: 5,
                total: 20,
                categories: [
                    { name: 'Donjons', completed: 1, total: 2, items: [
                        { id: 10, name: 'Haut-fait A', is_completed: true, icon_url: 'https://render.example/a.jpg' },
                        { id: 11, name: 'Haut-fait B', is_completed: false },
                    ] },
                ],
            },
        },
    },
};

const mountTab = (state = {}) => mountWithPlugins(AchievementsTab, {
    initialState: { character: { character: characterData, crossCharacter: null, ...state } },
    stubActions: false,
});

describe('AchievementsTab', () => {
    it('titles the sub-tab and sums up the expansion', async () => {
        const wrapper = await mountTab();

        expect(wrapper.find('h2').text()).toBe('Hauts-faits');
        expect(wrapper.find('[data-percent]').text()).toBe('25 %');
    });

    it('groups the achievements by category', async () => {
        const wrapper = await mountTab();

        expect(wrapper.find('h3').text()).toBe('Catégories de hauts-faits');
        expect(wrapper.find('[data-group] > button').text()).toContain('Donjons');
    });

    it('shows each achievement with its icon, linked to Wowhead', async () => {
        const wrapper = await mountTab();

        await wrapper.find('[data-group] > button').trigger('click');

        expect(wrapper.find('[data-group] li img').attributes('src')).toBe('https://render.example/a.jpg');
        expect(wrapper.find('[data-group] li a').attributes('href')).toBe('https://www.wowhead.com/fr/achievement=10');
    });

    it('names the character who earned it elsewhere', async () => {
        const wrapper = await mountTab({ crossCharacter: { completedAchievementIds: [11], achievementOwners: { 11: 'Jaina' } } });

        await wrapper.find('[data-group] > button').trigger('click');

        expect(wrapper.text()).toContain('Fait par Jaina');
    });
});
