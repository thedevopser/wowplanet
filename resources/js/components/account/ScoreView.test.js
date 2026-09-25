import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/mon-compte/score', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../../tests/helpers';
import ScoreView from './ScoreView.vue';

vi.mock('axios');

const dimension = (key, label, completed, total, score, applicable = true) =>
    ({ key, label, completed, total, score, applicable, weight: 0.1 });

const virtualProfile = {
    characterCount: 3,
    cachedAt: '2024-03-01T10:00:00Z',
    collections: {
        1: {
            quests: { completed: 50, total: 100, zones: [] },
            achievements: { completed: 30, total: 100, categories: [] },
            reputations: { completed: 10, total: 20 },
        },
    },
    mounts: [{ id: 1, is_completed: true, source: 'Drop' }, { id: 2, is_completed: false, source: 'Drop' }],
    pets: [{ id: 1, is_completed: true, source: 'Quête' }],
    decor: [{ id: 1, is_completed: true, source: 'Craft' }],
    professions: [{ profession_id: 1, expansions: { 1: { completed: 5, total: 10, skill_points: 50, max_skill_points: 100 } } }],
    score: {
        version: 2,
        global: 42.5,
        rank: 'Commun',
        dimensions: [
            dimension('quests', 'Quêtes', 50, 100, 50),
            dimension('achievements', 'Hauts-faits', 30, 100, 30),
            dimension('reputations', 'Réputations', 10, 20, 50),
            dimension('raids', 'Raids', 0, 0, 0, false),
            dimension('mounts', 'Montures', 1, 2, 50),
            dimension('transmog', 'Garde-robe', 300, 1000, 30),
            dimension('pets', 'Mascottes', 1, 1, 100),
            dimension('decor', 'Décorations', 1, 1, 100),
            dimension('professions', 'Métiers', 5, 10, 50),
        ],
    },
};

const stubs = { ScoreRadar: true, ShareScoreModal: true };

const mountPage = async () => {
    const wrapper = await mountWithPlugins(ScoreView, { stubs });
    return wrapper;
};

describe('ScoreView', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('holds the room of the score while it loads', async () => {
        axios.get.mockReturnValue(new Promise(() => {})); // never resolves

        const wrapper = await mountPage();

        expect(wrapper.find('[role="status"][aria-busy="true"]').text()).toContain('Chargement de votre score');
        expect(wrapper.findComponent({ name: 'LoadingSpinner' }).exists()).toBe(false);
    });

    it('transitions to computing state and shows progress', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'computing', progress: { loaded: 1, errors: 0, total: 3, current: 'CharName' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        const status = wrapper.find('[data-computing] [role="status"]');
        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.text()).toContain('Analyse de CharName…');
        expect(wrapper.text()).toContain('1 / 3 personnages chargés');
        expect(wrapper.find('[role="progressbar"]').attributes('aria-label')).toBe('Personnages analysés : 1 sur 3');
    });

    it('counts the characters that could not be read', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'computing', progress: { loaded: 1, errors: 2, total: 4, current: 'CharName' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('2 erreurs');
        expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('3');
    });

    it('transitions to ready state with score data', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'ready', data: virtualProfile },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Score du compte');
        expect(wrapper.text()).toContain('3 personnages');
        expect(wrapper.findComponent({ name: 'ScoreRadar' }).exists()).toBe(true);
        expect(wrapper.text()).toContain('Détail par dimension');
        expect(wrapper.text()).toContain('Garde-robe');
        expect(wrapper.text()).toContain('formule v2');
        // Les raids, sans données, sortent du radar mais restent listés.
        expect(wrapper.findComponent({ name: 'ScoreRadar' }).props('axes')).toHaveLength(8);
        expect(wrapper.text()).toContain('Non applicable');
    });

    it('shows error state on API error', async () => {
        axios.get.mockRejectedValueOnce({
            response: { status: 500, data: { message: 'Server error' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        const alert = wrapper.find('[role="alert"]');
        expect(alert.text()).toContain('Server error');

        axios.get.mockReturnValueOnce(new Promise(() => {}));
        await alert.find('button').trigger('click');

        expect(alert.find('button').text()).toContain('Réessayer');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('explains an account without characters to score', async () => {
        axios.get.mockRejectedValueOnce({
            response: { status: 401 },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun score à afficher');
        expect(wrapper.text()).not.toContain('Connectez-vous');
    });

    it('refresh button triggers score refresh', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'ready', data: virtualProfile },
        });

        const wrapper = await mountPage();
        await flushPromises();

        axios.post.mockResolvedValueOnce({});
        axios.get.mockReturnValueOnce(new Promise(() => {})); // polling restarts

        const refreshButton = wrapper.findAllComponents({ name: 'Button' }).find((button) => button.text().includes('Recalculer'));
        expect(refreshButton.exists()).toBe(true);

        await refreshButton.trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/account/score/refresh');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('cleans up polling on unmount', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'computing', progress: { loaded: 1, errors: 0, total: 3, current: 'CharName' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        const clearTimeoutSpy = vi.spyOn(global, 'clearTimeout');

        wrapper.unmount();

        expect(clearTimeoutSpy).toHaveBeenCalled();
        clearTimeoutSpy.mockRestore();
    });

    it('polls again after computing state with 2500ms delay', async () => {
        axios.get
            .mockResolvedValueOnce({
                data: { status: 'computing', progress: { loaded: 1, errors: 0, total: 3, current: 'CharName' } },
            })
            .mockResolvedValueOnce({
                data: { status: 'ready', data: virtualProfile },
            });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Analyse de CharName…');
        expect(axios.get).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(2500);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Score du compte');
    });
});
