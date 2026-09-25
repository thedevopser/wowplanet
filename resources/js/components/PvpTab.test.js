import { vi, describe, it, expect, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';

import { mountWithPlugins } from '../tests/helpers';
import PvpTab from './PvpTab.vue';

vi.mock('axios');

const payload = {
    season_id: 40,
    honor_level: 500,
    honorable_kills: 12345,
    best_rating: 1842,
    battlegrounds: { played: 16, won: 9, lost: 7, win_rate: 56.3 },
    groups: [
        {
            key: 'arena',
            label: 'Arène',
            brackets: [
                {
                    slug: '2v2',
                    label: 'Arène 2c2',
                    spec: null,
                    rating: 1400,
                    tier_name: 'Combattant II',
                    tier_icon_url: 'https://render.test/combatant.jpg',
                    played: 40,
                    won: 22,
                    lost: 18,
                    win_rate: 55,
                    weekly: { played: 4, won: 3, lost: 1 },
                },
                {
                    slug: '3v3',
                    label: 'Arène 3c3',
                    spec: null,
                    rating: 1842,
                    tier_name: 'Duelliste',
                    tier_icon_url: null,
                    played: 100,
                    won: 55,
                    lost: 45,
                    win_rate: 55,
                    weekly: { played: 0, won: 0, lost: 0 },
                },
            ],
        },
        {
            key: 'shuffle',
            label: 'Mêlée solo',
            brackets: [
                {
                    slug: 'shuffle-priest-shadow',
                    label: 'Mêlée solo — Ombre',
                    spec: 'Ombre',
                    rating: 1700,
                    tier_name: null,
                    tier_icon_url: null,
                    played: 30,
                    won: 15,
                    lost: 15,
                    win_rate: 50,
                    weekly: { played: 6, won: 3, lost: 3 },
                },
            ],
        },
    ],
};

function mountTab() {
    return mountWithPlugins(PvpTab, {
        props: { realm: 'hyjal', name: 'thrall' },
    });
}

describe('PvpTab', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows a loading state while fetching', async () => {
        axios.get.mockReturnValue(new Promise(() => {}));

        const wrapper = await mountTab();

        expect(wrapper.find('[data-testid="pvp-loading"]').exists()).toBe(true);
    });

    it('requests the pvp endpoint once for the given character', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        await mountTab();
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(axios.get).toHaveBeenCalledWith('/api/character/hyjal/thrall/pvp');
    });

    it('renders the season header', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        const header = wrapper.find('[data-testid="pvp-header"]').text();
        expect(header).toContain('1842');
        expect(header).toContain('500');
    });

    it('renders every group and bracket', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.findAll('[data-testid^="pvp-group-"]')).toHaveLength(2);
        expect(wrapper.findAll('[data-testid^="pvp-bracket-"]')).toHaveLength(3);

        const bracket = wrapper.find('[data-testid="pvp-bracket-3v3"]').text();
        expect(bracket).toContain('Arène 3c3');
        expect(bracket).toContain('1842');
        expect(bracket).toContain('Duelliste');
        expect(bracket).toContain('55');
    });

    it('renders the tier icon when available', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.find('[data-testid="pvp-bracket-2v2"] img').attributes('src'))
            .toBe('https://render.test/combatant.jpg');
        expect(wrapper.find('[data-testid="pvp-bracket-3v3"] img').exists()).toBe(false);
    });

    it('shows weekly matches only when some were played', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.find('[data-testid="pvp-bracket-2v2"]').text()).toContain('Cette semaine');
        expect(wrapper.find('[data-testid="pvp-bracket-3v3"]').text()).not.toContain('Cette semaine');
    });

    it('renders unrated battleground totals', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        const battlegrounds = wrapper.find('[data-testid="pvp-battlegrounds"]');
        expect(battlegrounds.exists()).toBe(true);
        expect(battlegrounds.text()).toContain('16');
        expect(battlegrounds.text()).toContain('56.3');
    });

    it('hides battleground totals when none were played', async () => {
        axios.get.mockResolvedValue({
            data: { pvp: { ...payload, battlegrounds: { played: 0, won: 0, lost: 0, win_rate: 0 } } },
        });

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.find('[data-testid="pvp-battlegrounds"]').exists()).toBe(false);
    });

    it('shows the empty state when the character has no pvp data', async () => {
        axios.get.mockResolvedValue({ data: { pvp: null } });

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.text()).toContain('Aucune donnée PvP');
        expect(wrapper.findAll('[data-testid^="pvp-group-"]')).toHaveLength(0);
    });

    it('shows an error state when the request fails', async () => {
        axios.get.mockRejectedValue(new Error('network'));

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.text()).toContain('Impossible de charger');
    });

    it('holds the room of the content while it loads, and says so', async () => {
        axios.get.mockReturnValue(new Promise(() => {}));

        const wrapper = await mountTab();
        const loading = wrapper.find('[data-testid="pvp-loading"]');

        expect(loading.attributes('aria-busy')).toBe('true');
        expect(loading.text()).toContain('Chargement du PvP');
        expect(wrapper.findComponent({ name: 'Skeleton' }).exists()).toBe(true);
    });

    it('offers to retry a failed request', async () => {
        axios.get.mockRejectedValueOnce(new Error('network')).mockResolvedValueOnce({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        await wrapper.findAll('button').find((button) => button.text().includes('Réessayer')).trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="pvp-header"]').exists()).toBe(true);
    });

    it('titles the sub-tab in a h2, each mode in a h3', async () => {
        axios.get.mockResolvedValue({ data: { pvp: payload } });

        const wrapper = await mountTab();
        await flushPromises();

        expect(wrapper.find('h2').text()).toBe('Joueur contre joueur');
        expect(wrapper.findAll('h3')).toHaveLength(2);
    });
});
