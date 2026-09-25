import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import { qualityColor } from '../utils/wowColors';
import RaidsTab from './RaidsTab.vue';
import { mountWithPlugins } from '../tests/helpers';

const STORAGE_KEY = 'wowplanet-raids-collapsed';

const boss = (id, name, at = 1775411018000) => ({ id, name, last_kill_timestamp: at });

function makeRaid(overrides = {}) {
    return {
        instance_id: 1307,
        instance_name: 'The Voidspire',
        modes: [
            { difficulty_type: 'LFR', difficulty_label: 'LFR', completed_count: 3, total_count: 3, encounters: [boss(1, 'Averzian'), boss(2, 'Vorasius'), boss(3, 'Salhadaar')] },
            { difficulty_type: 'MYTHIC', difficulty_label: 'Mythique', completed_count: 1, total_count: 3, encounters: [boss(1, 'Averzian')] },
        ],
        ...overrides,
    };
}

const mountTab = (raids) => mountWithPlugins(RaidsTab, { initialState: { character: { character: { raids } } } });

const raidSection = (wrapper, id = 1307) => wrapper.find(`[data-raid="${id}"]`);

beforeEach(() => {
    localStorage.clear();
});

describe('RaidsTab', () => {
    it('explains a character without raid progress', async () => {
        const wrapper = await mountTab(null);

        expect(wrapper.text()).toContain('Aucune progression de raid pour la saison en cours.');
    });

    it('titles the sub-tab in a h2, each raid in a h3', async () => {
        const wrapper = await mountTab([makeRaid()]);

        expect(wrapper.find('h2').text()).toBe('Raids');
        expect(wrapper.find('h3').text()).toContain('The Voidspire');
    });

    it('sums up every difficulty in its game colour, with a gauge per boss', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const summaries = raidSection(wrapper).findAll('[data-difficulty]');

        expect(summaries.map((entry) => entry.attributes('data-difficulty'))).toEqual(['LFR', 'NORMAL', 'HEROIC', 'MYTHIC']);
        expect(summaries[0].text()).toContain('3/3');
        expect(summaries[0].text()).toContain('Terminé');
        expect(summaries[0].find('[role="img"]').attributes('aria-label')).toBe('Outil Raids : 3 boss vaincus sur 3');
        expect(summaries[0].find('[data-pip="filled"]').attributes('style')).toContain(qualityColor('UNCOMMON').base);
        expect(summaries[1].text()).toContain('Non entamé');
        expect(summaries[3].text()).toContain('1/3');
    });

    it('lays out the bosses against the difficulties started, in a real table', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const table = raidSection(wrapper).find('table');

        expect(table.find('caption').text()).toContain('The Voidspire');
        expect(table.findAll('thead th').map((cell) => cell.text())).toEqual(['Boss', 'LFR', 'M']);
        expect(table.findAll('thead th').every((cell) => cell.attributes('scope') === 'col')).toBe(true);
        expect(table.findAll('tbody th').map((cell) => cell.text())).toEqual(['Averzian', 'Vorasius', 'Salhadaar']);
        expect(table.findAll('tbody th').every((cell) => cell.attributes('scope') === 'row')).toBe(true);
    });

    it('dates each kill and says which bosses are still standing', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const [, second] = raidSection(wrapper).findAll('tbody tr');
        const cells = second.findAll('td');

        expect(cells[0].text()).toContain('Vaincu le');
        expect(cells[1].find('.sr-only').text()).toBe('Non vaincu');
    });

    it('counts the bosses never defeated, without name', async () => {
        const raid = makeRaid({ modes: [{ difficulty_type: 'NORMAL', difficulty_label: 'Normal', completed_count: 1, total_count: 4, encounters: [boss(1, 'Averzian')] }] });
        const wrapper = await mountTab([raid]);

        expect(raidSection(wrapper).find('tfoot').text()).toContain('3 boss jamais vaincus');
    });

    it('renders every raid', async () => {
        const wrapper = await mountTab([makeRaid(), makeRaid({ instance_id: 1308, instance_name: 'Nerub-ar Palace' })]);

        expect(wrapper.findAll('[data-raid]')).toHaveLength(2);
    });

    it('folds the boss table from the raid title, and says so', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const toggle = raidSection(wrapper).find('h3 button');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(raidSection(wrapper).find(`#${toggle.attributes('aria-controls')}`).exists()).toBe(true);

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(raidSection(wrapper).find('table').exists()).toBe(false);
        expect(raidSection(wrapper).findAll('[data-difficulty]')).toHaveLength(4);
    });

    it('folds each raid independently', async () => {
        const wrapper = await mountTab([makeRaid(), makeRaid({ instance_id: 1308 })]);

        await raidSection(wrapper).find('h3 button').trigger('click');

        expect(raidSection(wrapper, 1308).find('table').exists()).toBe(true);
    });

    it('remembers the folded raids, and forgets an unfolded one', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const toggle = raidSection(wrapper).find('h3 button');

        await toggle.trigger('click');
        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([1307]);

        await toggle.trigger('click');
        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([]);
    });

    it('restores the folds of the previous visit', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([1307]));

        const wrapper = await mountTab([makeRaid()]);

        expect(raidSection(wrapper).find('table').exists()).toBe(false);
    });

    it('ignores corrupted folds', async () => {
        localStorage.setItem(STORAGE_KEY, '{oops');

        const wrapper = await mountTab([makeRaid()]);

        expect(raidSection(wrapper).find('table').exists()).toBe(true);
    });

    it('keeps working when the browser refuses to store the folds', async () => {
        const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('QuotaExceededError');
        });
        const wrapper = await mountTab([makeRaid()]);

        await raidSection(wrapper).find('h3 button').trigger('click');

        expect(raidSection(wrapper).find('table').exists()).toBe(false);
        setItem.mockRestore();
    });

    it('shows first the most recent raid, the one of the current tier', async () => {
        const wrapper = await mountTab([makeRaid({ instance_id: 1307 }), makeRaid({ instance_id: 1320 }), makeRaid({ instance_id: 1314 })]);

        expect(wrapper.findAll('[data-raid]').map((entry) => entry.attributes('data-raid'))).toEqual(['1320', '1314', '1307']);
    });
});
