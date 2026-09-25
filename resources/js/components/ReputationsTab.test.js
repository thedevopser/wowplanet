import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/character/hyjal/arthas/progression/reputations', props: {} });

    return { usePage: () => page, router: { replace: vi.fn() } };
});

import { standingColor } from '../utils/wowColors';
import { mountWithPlugins } from '../tests/helpers';
import ReputationsTab from './ReputationsTab.vue';

const characterData = {
    name: 'Arthas',
    collections: {
        11: {
            reputations: {
                completed: 2,
                total: 5,
                factions: [
                    { id: 2600, name: 'Council of Dornogal', standing_name: 'Exalté', tier: 7, value: 0, max: 0, raw: 42999, renown_level: 0, completed: true },
                    { id: 2601, name: 'Assemblée des Profondeurs', standing_name: 'Honoré', tier: 5, value: 5000, max: 12000, raw: 14000, renown_level: 0, completed: false },
                    { id: 2602, name: 'Artisans de Dornogal', standing_name: 'Renom 25', tier: 0, value: 0, max: 2500, raw: 62500, renown_level: 25, completed: true },
                    { id: 2603, name: 'Bêtes de Khaz Algar', standing_name: 'Révéré', tier: 6, value: 8000, max: 21000, raw: 35000, renown_level: 0, completed: false },
                    { id: 2604, name: 'Explorateurs de Dornogal', standing_name: 'Renom 10', tier: 0, value: 1200, max: 2500, raw: 25000, renown_level: 10, completed: false, account_wide: true },
                ],
            },
        },
    },
};

const mountTab = (state = {}) => mountWithPlugins(ReputationsTab, {
    initialState: { character: { character: characterData, crossCharacter: null, ...state } },
    stubActions: false,
});

const card = (wrapper, name) => wrapper.findAll('[data-faction]').find((entry) => entry.find('a').text() === name);

describe('ReputationsTab', () => {
    it('titles the sub-tab and counts the finished factions', async () => {
        const wrapper = await mountTab();

        expect(wrapper.find('h2').text()).toBe('Réputations');
        expect(wrapper.text()).toContain('2 / 5');
    });

    it('lists the factions by name, linked to Wowhead', async () => {
        const wrapper = await mountTab();

        const links = wrapper.findAll('[data-faction] a');

        expect(links.map((link) => link.text())).toEqual(['Artisans de Dornogal', 'Assemblée des Profondeurs', 'Bêtes de Khaz Algar', 'Council of Dornogal', 'Explorateurs de Dornogal']);
        expect(links[0].attributes('href')).toBe('https://www.wowhead.com/fr/faction=2602');
    });

    it('colours each standing after its tier, a finished renown like exaltation', async () => {
        const wrapper = await mountTab();

        const badge = (name) => card(wrapper, name).findComponent({ name: 'Badge' });

        expect(badge('Assemblée des Profondeurs').props()).toEqual(expect.objectContaining({ tone: 'standing', value: '5' }));
        expect(badge('Artisans de Dornogal').props('value')).toBe('7');
        expect(badge('Explorateurs de Dornogal').props('value')).toBe('renown');
        expect(badge('Explorateurs de Dornogal').text()).toBe('Renom 10');
    });

    it('shows a labelled bar for a faction in progress only', async () => {
        const wrapper = await mountTab();

        expect(card(wrapper, 'Council of Dornogal').find('[role="progressbar"]').exists()).toBe(false);
        expect(card(wrapper, 'Artisans de Dornogal').find('[role="progressbar"]').exists()).toBe(false);

        const bar = card(wrapper, 'Assemblée des Profondeurs').findComponent({ name: 'ProgressBar' });

        expect(bar.props()).toEqual(expect.objectContaining({ value: 5000, max: 12000, color: standingColor('5').base }));
        expect(bar.props('ariaLabel').replace(/\s/g, ' ')).toBe('Assemblée des Profondeurs : 5 000 sur 12 000');
    });

    it('takes the best renown of the account for an account-wide faction', async () => {
        const wrapper = await mountTab({ crossCharacter: { bestFactionStandings: { 2604: { renown_level: 18, raw: 45000, tier: 0, completed: false, character_name: 'Jaina' } } } });

        expect(card(wrapper, 'Explorateurs de Dornogal').text()).toContain('Renom 18');
    });

    it('names a character with a better standing on a character-bound faction', async () => {
        const wrapper = await mountTab({ crossCharacter: { bestFactionStandings: { 2601: { raw: 20000, standing_name: 'Révéré', character_name: 'Jaina' } } } });

        expect(card(wrapper, 'Assemblée des Profondeurs').text()).toContain('Meilleur : Jaina — Révéré');
    });

    it('hides the finished factions on demand', async () => {
        const wrapper = await mountTab();

        await wrapper.findAll('button').find((button) => button.text().includes('Masquer les terminées')).trigger('click');

        expect(wrapper.findAll('[data-faction]')).toHaveLength(3);
    });

    it('hides the unstarted factions on demand, telling whether it does', async () => {
        const data = structuredClone(characterData);
        data.collections[11].reputations.factions.push({ id: 9, name: 'Zandalari', standing_name: 'Neutre', tier: 3, value: 0, max: 3000, raw: 0, renown_level: 0, completed: false, started: false });
        const wrapper = await mountTab({ character: data });
        const toggle = () => wrapper.findAll('button').find((button) => button.text().includes('Masquer les non commencées'));

        expect(toggle().attributes('aria-pressed')).toBe('false');
        expect(card(wrapper, 'Zandalari').text()).toContain('Non commencée');

        await toggle().trigger('click');

        expect(toggle().attributes('aria-pressed')).toBe('true');
        expect(card(wrapper, 'Zandalari')).toBeUndefined();
    });

    it('searches the factions by name', async () => {
        const wrapper = await mountTab();

        await wrapper.find('input[type="search"]').setValue('bêtes');

        expect(wrapper.findAll('[data-faction]')).toHaveLength(1);
    });

    it('explains an expansion without reputation', async () => {
        const data = structuredClone(characterData);
        data.collections[11].reputations = { completed: 0, total: 0, factions: [] };

        const wrapper = await mountTab({ character: data });

        expect(wrapper.text()).toContain('Aucune réputation pour cette extension.');
    });

    it('shows a finished friendship or companion like exaltation, a last level marked so', async () => {
        const data = structuredClone(characterData);
        data.collections[11].reputations.factions.push(
            { id: 8, name: 'Cartel', standing_name: 'Génie', tier: 8, value: 0, max: 0, raw: 20000, renown_level: 0, completed: true },
            { id: 9, name: 'Valeera', standing_name: 'Niveau 80', tier: 79, value: 0, max: 0, raw: 8989497, renown_level: 0, completed: true },
        );

        const wrapper = await mountTab({ character: data });
        const badge = (name) => card(wrapper, name).findComponent({ name: 'Badge' });

        expect(badge('Cartel').props('value')).toBe('7');
        expect(badge('Valeera').text()).toBe('Niveau 80 · max');
    });
});
