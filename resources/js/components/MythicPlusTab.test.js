import { describe, it, expect, vi, beforeEach } from 'vitest';

const theme = vi.hoisted(() => ({ effective: null }));

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');
    theme.effective = ref('dark');

    return { useTheme: () => ({ effective: theme.effective }) };
});

import { readableVariants } from '../utils/wowColors';
import MythicPlusTab from './MythicPlusTab.vue';
import { mountWithPlugins } from '../tests/helpers';

function makeRun(overrides = {}) {
    return {
        dungeon_id: 1,
        dungeon_name: 'Ara-Kara',
        level: 12,
        is_timed: true,
        map_score: 185.5,
        map_score_color: { r: 163, g: 53, b: 238 },
        duration_ms: 1920000,
        completed_at: 1709251200000,
        ...overrides,
    };
}

const mythicData = {
    season_id: 13,
    rating: 2450.5,
    rating_color: { r: 255, g: 128, b: 0 },
    best_runs: [
        makeRun(),
        makeRun({ dungeon_id: 1, level: 14, is_timed: false, map_score: 200 }),
        makeRun({ dungeon_id: 2, dungeon_name: 'Stonevault', level: 11, is_timed: false, map_score: 150 }),
    ],
};

function makeResilience(overrides = {}) {
    return {
        level: null,
        min_level: 12,
        max_level: 25,
        dungeons: [
            { dungeon_id: 1, name: 'Ara-Kara', best_timed_level: 12 },
            { dungeon_id: 2, name: 'Stonevault', best_timed_level: null },
        ],
        targets: Array.from({ length: 14 }, (_, index) => ({ level: 12 + index, remaining: index === 0 ? [2] : [1, 2] })),
        ...overrides,
    };
}

const mountTab = (mythicKeystone) => mountWithPlugins(MythicPlusTab, {
    initialState: { character: { character: { mythicKeystone } } },
});

const cards = (wrapper) => wrapper.findAll('[data-dungeon]');

beforeEach(() => {
    theme.effective.value = 'dark';
});

describe('MythicPlusTab', () => {
    it('explains a character without Mythic+ this season', async () => {
        const wrapper = await mountTab(null);

        expect(wrapper.text()).toContain('Aucune donnée Mythique+ pour la saison en cours.');
    });

    it('explains a season without any run', async () => {
        const wrapper = await mountTab({ ...mythicData, best_runs: [] });

        expect(wrapper.text()).toContain('Aucune course enregistrée cette saison.');
    });

    it('titles the sub-tab and puts the season rating forward, in its readable Blizzard colour', async () => {
        const wrapper = await mountTab(mythicData);

        expect(wrapper.find('h2').text()).toBe('Mythique+');
        expect(wrapper.text()).toContain('Saison 13');
        expect(wrapper.find('[data-rating]').text().replace(/\s/g, ' ')).toBe('2 451');
        expect(wrapper.find('[data-rating]').attributes('style')).toContain(readableVariants('#FF8000').onDark);

        theme.effective.value = 'light';
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-rating]').attributes('style')).toContain(readableVariants('#FF8000').onLight);
    });

    it('shows the resilience level beside the rating', async () => {
        const wrapper = await mountTab({ ...mythicData, resilience: makeResilience({ level: 13 }) });

        expect(wrapper.find('[data-resilience]').text()).toBe('Rési+13');
    });

    it('says so when the character has no resilience', async () => {
        const wrapper = await mountTab({ ...mythicData, resilience: makeResilience({ level: null }) });

        expect(wrapper.find('[data-resilience]').text()).toBe('RésiAucune');
    });

    it('says nothing about resilience when the dungeons of the season are unknown', async () => {
        const withoutKey = await mountTab(mythicData);
        const withNull = await mountTab({ ...mythicData, resilience: null });

        expect(withoutKey.find('[data-resilience]').exists()).toBe(false);
        expect(withNull.find('[data-resilience]').exists()).toBe(false);
    });

    it('puts the resilience goal between the header and the dungeon cards', async () => {
        const wrapper = await mountTab({ ...mythicData, resilience: makeResilience() });
        const goal = wrapper.find('[data-resilience-goal]');

        expect(goal.exists()).toBe(true);
        expect(goal.text()).toContain('1 donjon restant sur 2');
        expect(goal.element.compareDocumentPosition(wrapper.find('[data-dungeon]').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('has no resilience goal when the dungeons of the season are unknown', async () => {
        const wrapper = await mountTab({ ...mythicData, resilience: null });

        expect(wrapper.find('[data-resilience-goal]').exists()).toBe(false);
    });

    it('sums up the season: dungeons played, highest key in time, dungeons done in time', async () => {
        const wrapper = await mountTab(mythicData);
        const stats = Object.fromEntries(wrapper.findAll('[data-season-stats] div').map((entry) => [entry.find('dt').text(), entry.find('dd').text()]));

        expect(stats).toEqual({ 'Donjons joués': '2', 'Plus haute clé dans les temps': '+12', 'Donjons dans les temps': '1 / 2' });
    });

    it('gives each dungeon one card, led by its best run in time, the highest first', async () => {
        const wrapper = await mountTab(mythicData);

        expect(cards(wrapper).map((card) => card.find('h3').text())).toEqual(['Ara-Kara', 'Stonevault']);
        expect(cards(wrapper)[0].find('[data-key]').text()).toBe('+12');
    });

    it('puts the key level forward in the colour of its score, with the score, the duration and the date', async () => {
        const wrapper = await mountTab(mythicData);
        const lead = cards(wrapper)[0].find('[data-lead]');

        expect(cards(wrapper)[0].find('[data-key]').attributes('style')).toContain(readableVariants('#A335EE').onDark);
        expect(lead.text()).toContain('186');
        expect(lead.text()).toContain('32:00');
        expect(lead.text()).toContain('2024');
    });

    it('says in words whether a run was in time', async () => {
        const wrapper = await mountTab(mythicData);

        expect(cards(wrapper)[0].find('[data-lead]').text()).toContain('Dans les temps');
        expect(cards(wrapper)[1].find('[data-lead]').text()).toContain('Hors temps');
    });

    it('keeps the best run of the other kind in the same card', async () => {
        const wrapper = await mountTab(mythicData);
        const other = cards(wrapper)[0].find('[data-other]');

        expect(other.text()).toContain('Hors temps');
        expect(other.text()).toContain('+14');
        expect(other.text()).toContain('200');
        expect(cards(wrapper)[1].find('[data-other]').exists()).toBe(false);
    });

    it('does not show the group of a run', async () => {
        const wrapper = await mountTab(mythicData);

        expect(wrapper.find('details').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Composition du groupe');
    });
});
