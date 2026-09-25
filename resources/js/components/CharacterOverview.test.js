import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';

vi.mock('@inertiajs/vue3', () => ({ router: { push: vi.fn() } }));

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import { router } from '@inertiajs/vue3';
import { dimensionColor, readableVariants } from '../utils/wowColors';
import CharacterOverview from './CharacterOverview.vue';

const SCORE = { global: 42.5, rank: 'Rare', version: 2, dimensions: [{ key: 'mounts', label: 'Montures', completed: 1, total: 2, score: 50, applicable: true }] };

const CHARACTER = {
    name: 'Arthas',
    realm: 'Hyjal',
    class: 'Chevalier de la mort',
    race: 'Humain',
    level: 80,
    classId: 6,
    mountsCount: 150,
    petsCount: 200,
    decorCount: 12,
    exaltedCount: 42,
    achievementPoints: 31190,
    mythicKeystone: { rating: 2663.4, rating_color: { r: 255, g: 128, b: 0 } },
    score: SCORE,
    mounts: [{ id: 1, name: 'A', source: 'Raid', is_completed: true }, { id: 2, name: 'B', source: 'Raid', is_completed: false }],
};

let wrapper;

function mountOverview(character = CHARACTER) {
    wrapper = mount(CharacterOverview, {
        props: { character, realm: 'hyjal', name: 'arthas' },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: { ScorePanel: true } },
    });

    return wrapper;
}

afterEach(() => wrapper?.unmount());

describe('CharacterOverview', () => {
    it('gathers the key counters of the character', () => {
        mountOverview();

        const tiles = wrapper.findAll('[data-stat-value]').map((tile) => tile.text().replace(/\s/g, ' '));

        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Cote Mythique+');
        expect(tiles).toEqual(['150', '200', '12', '42', '31 190', '2 663']);
    });

    it('colours each counter after its dimension of the score', () => {
        mountOverview();

        const tiles = wrapper.findAllComponents({ name: 'StatTile' });

        expect(tiles.slice(0, 5).map((tile) => tile.props('ruleColor'))).toEqual(
            ['mounts', 'pets', 'decor', 'reputations', 'achievements'].map((key) => dimensionColor(key).base),
        );
        expect(tiles[0].props('valueColor')).toBe(dimensionColor('mounts').onDark);
    });

    it('colours the Mythic+ rating as Blizzard does, readably', () => {
        mountOverview();

        const rating = wrapper.findAllComponents({ name: 'StatTile' }).at(-1);

        expect(rating.props('ruleColor')).toBe('#FF8000');
        expect(rating.props('valueColor')).toBe(readableVariants('#FF8000').onDark);
    });

    it('leaves out the Mythic+ rating of a character without one', () => {
        mountOverview({ ...CHARACTER, mythicKeystone: null });

        expect(wrapper.text()).not.toContain('Cote Mythique+');
    });

    it('hands the score, its recommendations and the share data to the panel', () => {
        mountOverview();

        const panel = wrapper.findComponent({ name: 'ScorePanel' });

        expect(panel.props('score')).toEqual(SCORE);
        expect(panel.props('title')).toBe('Score de complétion');
        expect(panel.props('recommendations').map((rec) => rec.key)).toEqual(['mount:Raid']);
        expect(panel.props('shareData')).toEqual(expect.objectContaining({ variant: 'personal', characterName: 'Arthas', globalScore: 42.5, rank: 'Rare' }));
    });

    it('links each dimension to the sub-tab that details it', () => {
        mountOverview();

        expect(wrapper.findComponent({ name: 'ScorePanel' }).props('dimensionLinks')).toEqual(expect.objectContaining({
            mounts: '/character/hyjal/arthas/collections/montures',
            quests: '/character/hyjal/arthas/progression/quetes',
            raids: '/character/hyjal/arthas/endgame/raids',
        }));
    });

    it('moves to the sub-tab of a followed dimension without a request', async () => {
        mountOverview();

        wrapper.findComponent({ name: 'ScorePanel' }).vm.$emit('navigate', 'mounts');

        expect(router.push.mock.calls.at(-1)[0].url).toBe('/character/hyjal/arthas/collections/montures');
    });

    it('explains the absence of a score', () => {
        mountOverview({ ...CHARACTER, score: null });

        expect(wrapper.findComponent({ name: 'ScorePanel' }).exists()).toBe(false);
        expect(wrapper.text()).toContain('Score indisponible');
    });
});
