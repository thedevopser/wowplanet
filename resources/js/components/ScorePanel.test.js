import { describe, it, expect, vi, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import ScorePanel from './ScorePanel.vue';

const dimension = (key, label, completed, total, score, applicable = true) => ({ key, label, completed, total, score, applicable, weight: 0.1 });

const SCORE = {
    global: 42.5,
    rank: 'Rare',
    version: 2,
    dimensions: [
        dimension('quests', 'Quêtes', 40, 100, 40),
        dimension('mounts', 'Montures', 300, 600, 50),
        dimension('raids', 'Raids', 0, 0, 0, false),
    ],
};

const RECOMMENDATIONS = [{
    key: 'mount:Raid',
    name: 'Raid',
    dimension: 'Montures',
    dimensionKey: 'mounts',
    completed: 3,
    total: 4,
    missing: 1,
    percent: 75,
    missingItems: [{ id: 9, name: 'Invincible', wowheadUrl: 'https://www.wowhead.com/fr/spell=72286' }],
    missingMore: 2,
}];

let wrapper;

function mountPanel(props = {}) {
    wrapper = mount(ScorePanel, {
        props: { score: SCORE, title: 'Score de complétion', recommendations: RECOMMENDATIONS, shareData: {}, ...props },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: { ShareScoreModal: true } },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('ScorePanel', () => {
    it('titles the panel in a h2 and its parts in h3', () => {
        mountPanel();

        expect(wrapper.find('h2').text()).toBe('Score de complétion');
        expect(wrapper.findAll('h3').map((heading) => heading.text())).toEqual(['Détail par dimension', 'Il vous reste…']);
    });

    it('says how many dimensions count and which formula applies', () => {
        mountPanel();

        expect(wrapper.text()).toContain('2 dimensions');
        expect(wrapper.find('a[href="/faq"]').text()).toBe('formule v2');
    });

    it('draws the radar on the applicable dimensions only', () => {
        mountPanel();

        expect(wrapper.findComponent({ name: 'ScoreRadar' }).props('axes')).toEqual([{ label: 'Quêtes', score: 40 }, { label: 'Montures', score: 50 }]);
    });

    it('shows the global score and its rank', () => {
        mountPanel();

        expect(wrapper.find('[data-global-score]').text()).toBe('42,5');
        expect(wrapper.find('[data-rank]').text()).toBe('Rare');
    });

    it('gives each dimension a card with a labelled progress bar', () => {
        mountPanel();

        const cards = wrapper.findAll('[data-dimension]');

        expect(cards).toHaveLength(3);
        expect(cards[0].text()).toContain('40 %');
        expect(cards[0].find('[role="progressbar"]').attributes('aria-valuenow')).toBe('40');
        expect(cards[2].text()).toContain('Non applicable');
    });

    it('links a dimension card to its detail when given an address', async () => {
        mountPanel({ dimensionLinks: { mounts: '/character/hyjal/arthas/collections/montures' } });

        const link = wrapper.find('[data-dimension="mounts"] a');

        expect(link.attributes('href')).toBe('/character/hyjal/arthas/collections/montures');
        expect(wrapper.find('[data-dimension="quests"] a').exists()).toBe(false);

        await link.trigger('click', { button: 0 });

        expect(wrapper.emitted('navigate')).toEqual([['mounts']]);
    });

    it('lets a modified click open the link as usual', async () => {
        mountPanel({ dimensionLinks: { mounts: '/character/hyjal/arthas/collections/montures' } });

        await wrapper.find('[data-dimension="mounts"] a').trigger('click', { button: 0, ctrlKey: true });

        expect(wrapper.emitted('navigate')).toBeUndefined();
    });

    it('expands a recommendation from a real button', async () => {
        mountPanel();
        const toggle = wrapper.find('[data-recommendation] button');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.text()).not.toContain('Invincible');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find(`#${toggle.attributes('aria-controls')}`).text()).toContain('Invincible');
        expect(wrapper.text()).toContain('et 2 autres');
    });

    it('hides the recommendations when there are none', () => {
        mountPanel({ recommendations: [] });

        expect(wrapper.text()).not.toContain('Il vous reste');
    });

    it('opens the share dialog from « Partager ce score »', async () => {
        mountPanel();

        await wrapper.findAll('button').find((button) => button.text().includes('Partager ce score')).trigger('click');
        await flushPromises();

        expect(wrapper.findComponent({ name: 'ShareScoreModal' }).props('show')).toBe(true);
    });

    it('uses no raw palette class nor arbitrary size', () => {
        const html = mountPanel().html();

        expect(html).not.toMatch(/\b(?:bg|text|border)-(?:slate|blue|indigo|amber|white)(?:-\d+)?\b/);
        expect(html).not.toMatch(/text-\[\d+px\]/);
    });
});
