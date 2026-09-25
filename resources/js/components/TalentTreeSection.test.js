import { describe, it, expect, vi, beforeEach } from 'vitest';
import axios from 'axios';
import { mount } from '@vue/test-utils';
import TalentTreeSection from './TalentTreeSection.vue';

vi.mock('axios');

const makeNode = (id, x, y) => ({
    id,
    x,
    y,
    type: 'passive',
    selected_rank: 1,
    max_rank: 1,
    entries: [{ spell_id: id * 10, name: `Talent ${id}`, icon_url: null }],
});

const talentData = {
    class_name: 'Chevalier de la mort',
    spec_name: 'Sang',
    class_nodes: [makeNode(1, 0, 0)],
    spec_nodes: [makeNode(2, 0, 0), makeNode(3, 1, 0)],
    hero_trees: [
        { id: 10, name: 'Sangdragon', active: false, nodes: [makeNode(4, 0, 0)] },
        { id: 11, name: 'Cavalier maudit', active: true, nodes: [makeNode(5, 0, 0), makeNode(6, 1, 0), makeNode(7, 2, 0)] },
    ],
};

const mountSection = () => mount(TalentTreeSection, { props: { realm: 'hyjal', name: 'arthas' } });

async function mountExpanded(data = talentData) {
    axios.get = vi.fn().mockResolvedValue({ data });

    const wrapper = mountSection();
    await wrapper.find('button').trigger('click');
    await vi.waitFor(() => expect(wrapper.findAllComponents({ name: 'TalentTreeGrid' }).length).toBeGreaterThan(0));

    return wrapper;
}

beforeEach(() => vi.restoreAllMocks());

describe('TalentTreeSection', () => {
    it('stays collapsed and fetches nothing until it is opened', () => {
        axios.get = vi.fn();

        const wrapper = mountSection();

        expect(wrapper.text()).toContain('Talents');
        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).exists()).toBe(false);
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('fetches the talents of the character when opened', async () => {
        await mountExpanded();

        expect(axios.get).toHaveBeenCalledWith('/api/character/hyjal/arthas/talents');
    });

    it('encodes realm and name in the request', async () => {
        axios.get = vi.fn().mockResolvedValue({ data: talentData });

        const wrapper = mount(TalentTreeSection, { props: { realm: "conseil-des-ombres", name: 'Élune Ardente' } });
        await wrapper.find('button').trigger('click');

        expect(axios.get).toHaveBeenCalledWith('/api/character/conseil-des-ombres/%C3%89lune%20Ardente/talents');
    });

    it('fetches only once even after several open and close cycles', async () => {
        const wrapper = await mountExpanded();

        await wrapper.find('button').trigger('click');
        await wrapper.find('button').trigger('click');

        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('shows the specialization name once loaded', async () => {
        const wrapper = await mountExpanded();

        expect(wrapper.text()).toContain('Sang');
        expect(wrapper.text()).toContain('Chevalier de la mort');
    });

    it('shows the class tree first', async () => {
        const wrapper = await mountExpanded();

        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).props('nodes')).toHaveLength(1);
    });

    it('switches to the specialization tree', async () => {
        const wrapper = await mountExpanded();

        await wrapper.findAll('[role="tab"]').find(b => b.text() === 'Sang').trigger('mousedown', { button: 0 });

        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).props('nodes')).toHaveLength(2);
    });

    it('opens the hero tree on the one flagged active', async () => {
        const wrapper = await mountExpanded();

        await wrapper.findAll('[role="tab"]').find(b => b.text() === 'Talents héroïques').trigger('mousedown', { button: 0 });

        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).props('nodes')).toHaveLength(3);
    });

    it('lets another hero tree be selected', async () => {
        const wrapper = await mountExpanded();

        await wrapper.findAll('[role="tab"]').find(b => b.text() === 'Talents héroïques').trigger('mousedown', { button: 0 });
        await wrapper.findAll('button').find(b => b.text().includes('Sangdragon')).trigger('click');

        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).props('nodes')).toHaveLength(1);
    });

    it('falls back to the first hero tree when none is active', async () => {
        const wrapper = await mountExpanded({
            ...talentData,
            hero_trees: [{ id: 10, name: 'Sangdragon', active: false, nodes: [makeNode(4, 0, 0)] }],
        });

        await wrapper.findAll('[role="tab"]').find(b => b.text() === 'Talents héroïques').trigger('mousedown', { button: 0 });

        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).props('nodes')).toHaveLength(1);
    });

    it('hides the hero tree selector when there is only one tree', async () => {
        const wrapper = await mountExpanded({
            ...talentData,
            hero_trees: [{ id: 10, name: 'Sangdragon', active: true, nodes: [makeNode(4, 0, 0)] }],
        });

        await wrapper.findAll('[role="tab"]').find(b => b.text() === 'Talents héroïques').trigger('mousedown', { button: 0 });

        expect(wrapper.findAll('button').some(b => b.text().includes('Sangdragon'))).toBe(false);
    });

    it('offers no hero tab when the character has none', async () => {
        const wrapper = await mountExpanded({ ...talentData, hero_trees: [] });

        expect(wrapper.findAll('button').some(b => b.text() === 'Talents héroïques')).toBe(false);
    });

    it('shows an error message when the request fails', async () => {
        axios.get = vi.fn().mockRejectedValue(new Error('boom'));

        const wrapper = mountSection();
        await wrapper.find('button').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Impossible de charger les talents.'));

        expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).exists()).toBe(false);
    });

    it('retries the request after a failure', async () => {
        axios.get = vi.fn().mockRejectedValue(new Error('boom'));

        const wrapper = mountSection();
        await wrapper.find('button').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Impossible de charger les talents.'));

        axios.get.mockResolvedValue({ data: talentData });
        await wrapper.find('button').trigger('click');
        await wrapper.find('button').trigger('click');
        await vi.waitFor(() => expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).exists()).toBe(true));

        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('tells whether it is open, and which region it controls', async () => {
        const wrapper = await mountExpanded();
        const toggle = wrapper.find('button');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find(`#${toggle.attributes('aria-controls')}`).exists()).toBe(true);
    });

    it('offers its trees as real tabs', async () => {
        const wrapper = await mountExpanded();

        expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Chevalier de la mort', 'Sang', 'Talents héroïques']);
    });

    it('marks the chosen hero tree, the active one named so', async () => {
        const wrapper = await mountExpanded();
        await wrapper.findAll('[role="tab"]').find(b => b.text() === 'Talents héroïques').trigger('mousedown', { button: 0 });

        const heroButtons = wrapper.findAll('button[aria-pressed]');

        expect(heroButtons.map((button) => button.attributes('aria-pressed'))).toEqual(['false', 'true']);
        expect(heroButtons[1].text()).toContain('actif');
    });

    it('holds the room of the trees while they load', async () => {
        axios.get = vi.fn().mockReturnValue(new Promise(() => {}));

        const wrapper = mountSection();
        await wrapper.find('button').trigger('click');

        expect(wrapper.find('[aria-busy="true"]').text()).toContain('Chargement des talents');
    });

    it('offers to retry from the error itself', async () => {
        axios.get = vi.fn().mockRejectedValueOnce(new Error('boom')).mockResolvedValueOnce({ data: talentData });

        const wrapper = mountSection();
        await wrapper.find('button').trigger('click');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Impossible de charger les talents.'));

        await wrapper.findAll('button').find((button) => button.text().includes('Réessayer')).trigger('click');
        await vi.waitFor(() => expect(wrapper.findComponent({ name: 'TalentTreeGrid' }).exists()).toBe(true));
    });
});
