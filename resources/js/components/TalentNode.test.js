import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import TalentNode from './TalentNode.vue';

const makeNode = (overrides = {}) => ({
    id: 1,
    type: 'passive',
    x: 0,
    y: 0,
    selected_rank: 0,
    max_rank: 1,
    entries: [{ spell_id: 500, name: 'Frappe', icon_url: 'https://wow.zamimg.com/icon.jpg' }],
    ...overrides,
});

describe('TalentNode', () => {
    it('links to the wowhead page of the active spell', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode() } });

        expect(wrapper.find('a').attributes('href')).toBe('https://www.wowhead.com/fr/spell=500');
    });

    it('renders an empty placeholder when the node has no entry', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode({ entries: [] }) } });

        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.find('div').exists()).toBe(true);
    });

    it('renders an empty placeholder when entries are missing', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode({ entries: undefined }) } });

        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('highlights a node with a selected rank', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode({ selected_rank: 1 }) } });

        expect(wrapper.find('a').classes()).toContain('border-accent');
    });

    it('dims a node without a selected rank', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode() } });

        expect(wrapper.find('a').classes()).toContain('opacity-40');
    });

    it('renders a choice node as a circle', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode({ type: 'choice' }) } });

        expect(wrapper.find('a').classes()).toContain('rounded-full');
    });

    it('renders a passive node as a rounded square', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode() } });

        expect(wrapper.find('a').classes()).toContain('rounded-md');
    });

    it('shows the selected entry of a choice node', () => {
        const node = makeNode({
            type: 'choice',
            entries: [
                { spell_id: 600, name: 'Premier', icon_url: null },
                { spell_id: 601, name: 'Second', icon_url: null, selected: true },
            ],
        });

        const wrapper = mount(TalentNode, { props: { node } });

        expect(wrapper.find('a').attributes('href')).toContain('spell=601');
    });

    it('falls back to the first entry when no choice is selected', () => {
        const node = makeNode({
            type: 'choice',
            entries: [
                { spell_id: 600, name: 'Premier', icon_url: null },
                { spell_id: 601, name: 'Second', icon_url: null },
            ],
        });

        const wrapper = mount(TalentNode, { props: { node } });

        expect(wrapper.find('a').attributes('href')).toContain('spell=600');
    });

    it('shows the rank badge only when the node has several ranks', () => {
        const single = mount(TalentNode, { props: { node: makeNode() } });
        const multiple = mount(TalentNode, { props: { node: makeNode({ max_rank: 3, selected_rank: 2 }) } });

        expect(single.text()).not.toContain('/');
        expect(multiple.text()).toContain('2/3');
    });

    it('replaces the icon with a placeholder when it fails to load', async () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode() } });

        expect(wrapper.find('img').exists()).toBe(true);

        await wrapper.find('img').trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('renders a placeholder when the entry has no icon', () => {
        const node = makeNode({ entries: [{ spell_id: 500, name: 'Frappe', icon_url: null }] });

        const wrapper = mount(TalentNode, { props: { node } });

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('applies the requested size', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode(), size: 44 } });

        expect(wrapper.find('a').attributes('style')).toContain('width: 44px');
    });

    it('falls back to the default size', () => {
        const wrapper = mount(TalentNode, { props: { node: makeNode() } });

        expect(wrapper.find('a').attributes('style')).toContain('width: 56px');
    });
});
