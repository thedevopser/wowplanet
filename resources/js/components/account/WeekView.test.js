import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ url: '/mon-compte/semaine', props: {} }), router: { replace: vi.fn() } }));
vi.mock('axios');

import { mountWithPlugins } from '../../tests/helpers';
import WeekView from './WeekView.vue';

const slot = (threshold, reward) => ({ threshold, reached: reward !== null, reward });

const character = (name, { raid = {}, mythicPlus = {}, nextSlotGap = 1 } = {}) => ({
    name,
    realm: 'Dalaran',
    realmSlug: 'dalaran',
    level: 90,
    classId: 2,
    raid: { count: 0, slots: [slot(2, null), slot(4, null), slot(6, null)], remaining: [], ...raid },
    mythicPlus: { count: 0, slots: [slot(1, null), slot(4, null), slot(8, null)], missingDungeons: [], ...mythicPlus },
    nextSlotGap,
});

const gothdroid = character('Gothdroid', {
    raid: {
        count: 3,
        slots: [slot(2, 'HEROIC'), slot(4, null), slot(6, null)],
        remaining: [{ instanceId: 1307, name: 'La Flèche du Vide', difficulties: [
            { difficulty: 'NORMAL', encounters: ['Imperator Averzian', 'Vorasius'] },
            { difficulty: 'MYTHIC', encounters: ['Imperator Averzian', 'Vorasius', 'Salhadaar'] },
        ] }],
    },
    mythicPlus: { count: 1, slots: [slot(1, 12), slot(4, null), slot(8, null)], missingDungeons: ['Repaire de la Nexus-reine', 'Terrasse des Magistères'] },
});
const jaina = character('Jaina', { nextSlotGap: 2 });

const week = { characters: [gothdroid, jaina], belowMaxLevel: 9, maxLevel: 90, updatedAt: '2026-11-14T20:00:00Z' };
const ready = (data = week) => ({ data: { status: 'ready', data } });

const mountView = () => mountWithPlugins(WeekView);
const cards = (wrapper) => wrapper.findAll('[data-week-character]');

describe('WeekView', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows a card per character, in the order received', async () => {
        axios.get.mockResolvedValueOnce(ready());

        const wrapper = await mountView();
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/account/week');
        expect(cards(wrapper).map((card) => card.find('h3').text())).toEqual(['Gothdroid', 'Jaina']);
        expect(cards(wrapper)[0].text()).toContain('Dalaran · niveau 90');
    });

    it('heads each card with the slots of both vaults, what they are worth or what they need', async () => {
        axios.get.mockResolvedValueOnce(ready());

        const wrapper = await mountView();
        await flushPromises();

        const raid = cards(wrapper)[0].find('[data-vault="raid"]');
        expect(raid.findAll('[data-slot]').map((entry) => entry.text())).toEqual(['Héroïque', '4 boss', '6 boss']);
        expect(raid.findAll('[data-slot]').map((entry) => entry.attributes('data-reached'))).toEqual(['true', 'false', 'false']);
        expect(raid.text()).toContain('3 boss battus · encore 1 pour le 2e emplacement');

        const mythicPlus = cards(wrapper)[0].find('[data-vault="mythicPlus"]');
        expect(mythicPlus.findAll('[data-slot]').map((entry) => entry.text())).toEqual(['+12', '4 donjons', '8 donjons']);
        expect(mythicPlus.text()).toContain('1 donjon fait · encore 3 pour le 2e emplacement');

        const jainaRaid = cards(wrapper)[1].find('[data-vault="raid"]');
        expect(jainaRaid.text()).toContain('Aucun boss battu · encore 2 pour le 1er emplacement');
        expect(cards(wrapper)[1].find('[data-vault="mythicPlus"]').text()).toContain('Aucun donjon fait · encore 1 pour le 1er emplacement');
    });

    it('counts several dungeons done in the plural', async () => {
        const twoKeys = character('Two', { mythicPlus: { count: 2, slots: [slot(1, 10), slot(4, null), slot(8, null)] } });
        axios.get.mockResolvedValueOnce(ready({ ...week, characters: [twoKeys] }));

        const wrapper = await mountView();
        await flushPromises();

        expect(cards(wrapper)[0].find('[data-vault="mythicPlus"]').text()).toContain('2 donjons faits · encore 2 pour le 2e emplacement');
    });

    it('says a vault is full once every slot is reached', async () => {
        const full = character('Full', { raid: { count: 6, slots: [slot(2, 'MYTHIC'), slot(4, 'MYTHIC'), slot(6, 'HEROIC')] }, nextSlotGap: null });
        axios.get.mockResolvedValueOnce(ready({ ...week, characters: [full] }));

        const wrapper = await mountView();
        await flushPromises();

        expect(cards(wrapper)[0].find('[data-vault="raid"]').text()).toContain('6 boss battus · chambre forte remplie');
    });

    it('unfolds the bosses left by raid, the hardest difficulty first, and the dungeons without a key', async () => {
        axios.get.mockResolvedValueOnce(ready());

        const wrapper = await mountView();
        await flushPromises();

        const card = cards(wrapper)[0];
        const toggle = card.find('[data-show-remaining]');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(card.find('[data-remaining]').exists()).toBe(false);

        await toggle.trigger('click');

        const remaining = card.find('[data-remaining]');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(remaining.find('h4').text()).toBe('La Flèche du Vide');
        expect(remaining.findAll('[data-difficulty]').map((entry) => entry.text())).toEqual([
            'Mythique : Imperator Averzian, Vorasius, Salhadaar',
            'Normal : Imperator Averzian, Vorasius',
        ]);
        expect(remaining.find('[data-missing-dungeons]').text()).toBe('Donjons sans clé : Repaire de la Nexus-reine, Terrasse des Magistères');
    });

    it('offers nothing to unfold to a character with nothing left', async () => {
        axios.get.mockResolvedValueOnce(ready({ ...week, characters: [character('Full', { nextSlotGap: null })] }));

        const wrapper = await mountView();
        await flushPromises();

        expect(cards(wrapper)[0].find('[data-show-remaining]').exists()).toBe(false);
    });

    it('counts the characters left aside, and says what the Mythic+ count cannot see', async () => {
        axios.get.mockResolvedValueOnce(ready());

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-below-max-level]').text()).toBe('9 personnages sous le niveau 90 ne sont pas affichés.');
        expect(wrapper.find('[data-week-limits]').text()).toContain('meilleure clé de chaque donjon');
        expect(wrapper.find('[data-week-limits]').text()).toContain('Gouffres');
    });

    it('says nothing of the characters left aside when there is none', async () => {
        axios.get.mockResolvedValueOnce(ready({ ...week, belowMaxLevel: 0 }));

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-below-max-level]').exists()).toBe(false);
    });

    it('counts a single character left aside in the singular', async () => {
        axios.get.mockResolvedValueOnce(ready({ ...week, belowMaxLevel: 1 }));

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-below-max-level]').text()).toBe('1 personnage sous le niveau 90 n’est pas affiché.');
    });

    it('dates the week and updates it on demand', async () => {
        axios.get.mockResolvedValueOnce(ready()).mockReturnValueOnce(new Promise(() => {}));
        axios.post.mockResolvedValueOnce({});

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-updated-at]').text()).toMatch(/^Mis à jour le 14 nov\./);

        await wrapper.findAll('button').find((button) => button.text().includes('Mettre à jour')).trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/account/week/refresh');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('says it reads the characters while the computation runs', async () => {
        axios.get.mockResolvedValueOnce({ data: { status: 'computing', jobId: 'job-1' } });

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-computing] [role="status"]').text()).toContain('Lecture de vos personnages');
    });

    it('reports a failed computation and tries again on demand', async () => {
        axios.get.mockRejectedValueOnce({ response: { status: 502 } }).mockReturnValueOnce(new Promise(() => {}));

        const wrapper = await mountView();
        await flushPromises();

        const alert = wrapper.find('[role="alert"]');
        expect(alert.text()).toContain('Le calcul de votre semaine a échoué');

        await alert.find('button').trigger('click');

        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('says when no character of the account is at the highest level', async () => {
        axios.get.mockResolvedValueOnce(ready({ characters: [], belowMaxLevel: 0, maxLevel: 0, updatedAt: null }));

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun personnage à suivre cette semaine');
    });
});

describe('WeekView in english', () => {
    it('speaks english', async () => {
        axios.get.mockResolvedValueOnce(ready());

        const wrapper = await mountWithPlugins(WeekView, { locale: 'en' });
        await flushPromises();

        expect(wrapper.text()).toContain('What each character has left before the Wednesday reset.');
        expect(wrapper.text()).toContain('Update');
        expect(wrapper.find('[data-week-limits]').text()).toContain('The Mythic+ count only sees the best key of each dungeon');
        expect(cards(wrapper)[0].text()).toContain('level');
        expect(wrapper.text()).not.toMatch(/Mettre à jour|niveau|Donjons/);
    });
});
