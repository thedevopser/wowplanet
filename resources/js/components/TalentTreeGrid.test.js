import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import TalentTreeGrid from './TalentTreeGrid.vue';

const PADDING = 12;

const makeNode = (id, x, y, overrides = {}) => ({
    id,
    x,
    y,
    type: 'passive',
    selected_rank: 0,
    max_rank: 1,
    entries: [{ spell_id: id * 10, name: `Talent ${id}`, icon_url: null }],
    ...overrides,
});

function setViewportWidth(width) {
    Object.defineProperty(window, 'innerWidth', { value: width, writable: true, configurable: true });
}

afterEach(() => setViewportWidth(1024));

describe('TalentTreeGrid', () => {
    it('sizes the grid from the span of node coordinates', () => {
        setViewportWidth(1280);

        const wrapper = mount(TalentTreeGrid, {
            props: { nodes: [makeNode(1, 0, 0), makeNode(2, 2, 1)] },
        });

        const grid = wrapper.find('.relative.mx-auto');

        expect(grid.attributes('style')).toContain(`width: ${3 * (60 + 14) - 14 + PADDING * 2}px`);
        expect(grid.attributes('style')).toContain(`height: ${2 * (60 + 14) - 14 + PADDING * 2}px`);
    });

    it('positions nodes relative to the smallest coordinates', () => {
        setViewportWidth(1280);

        const wrapper = mount(TalentTreeGrid, {
            props: { nodes: [makeNode(1, 3, 5), makeNode(2, 4, 6)] },
        });

        const positions = wrapper.findAll('.relative.mx-auto > div.absolute').map(el => el.attributes('style'));

        expect(positions[0]).toContain(`left: ${PADDING}px`);
        expect(positions[0]).toContain(`top: ${PADDING}px`);
        expect(positions[1]).toContain(`left: ${PADDING + 74}px`);
        expect(positions[1]).toContain(`top: ${PADDING + 74}px`);
    });

    it('renders one talent node per entry', () => {
        const wrapper = mount(TalentTreeGrid, {
            props: { nodes: [makeNode(1, 0, 0), makeNode(2, 1, 0), makeNode(3, 2, 0)] },
        });

        expect(wrapper.findAllComponents({ name: 'TalentNode' })).toHaveLength(3);
    });

    it('renders a single cell grid when there is no node', () => {
        setViewportWidth(1280);

        const wrapper = mount(TalentTreeGrid, { props: { nodes: [] } });

        expect(wrapper.find('.relative.mx-auto').attributes('style')).toContain(`width: ${60 + PADDING * 2}px`);
        expect(wrapper.findAll('line')).toHaveLength(0);
    });

    it('uses the mobile cell size below 640px', () => {
        setViewportWidth(500);

        const wrapper = mount(TalentTreeGrid, { props: { nodes: [makeNode(1, 0, 0)] } });

        expect(wrapper.find('.relative.mx-auto').attributes('style')).toContain(`width: ${44 + PADDING * 2}px`);
    });

    it('uses the tablet cell size between 640px and 1024px', () => {
        setViewportWidth(800);

        const wrapper = mount(TalentTreeGrid, { props: { nodes: [makeNode(1, 0, 0)] } });

        expect(wrapper.find('.relative.mx-auto').attributes('style')).toContain(`width: ${52 + PADDING * 2}px`);
    });

    it('recomputes the cell size when the window is resized', async () => {
        setViewportWidth(1280);

        const wrapper = mount(TalentTreeGrid, { props: { nodes: [makeNode(1, 0, 0)] } });

        setViewportWidth(500);
        window.dispatchEvent(new Event('resize'));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.relative.mx-auto').attributes('style')).toContain(`width: ${44 + PADDING * 2}px`);
    });

    it('stops listening for resize once unmounted', async () => {
        setViewportWidth(1280);

        const wrapper = mount(TalentTreeGrid, { props: { nodes: [makeNode(1, 0, 0)] } });
        const html = wrapper.html();
        wrapper.unmount();

        setViewportWidth(500);
        expect(() => window.dispatchEvent(new Event('resize'))).not.toThrow();
        expect(html).toContain(`width: ${60 + PADDING * 2}px`);
    });

    it('draws a connection between a node and each node it unlocks', () => {
        const wrapper = mount(TalentTreeGrid, {
            props: { nodes: [makeNode(1, 0, 0, { unlocks: [2, 3] }), makeNode(2, 1, 0), makeNode(3, 2, 0)] },
        });

        expect(wrapper.findAll('line')).toHaveLength(2);
    });

    it('ignores an unlock pointing to a node absent from the tree', () => {
        const wrapper = mount(TalentTreeGrid, {
            props: { nodes: [makeNode(1, 0, 0, { unlocks: [99] })] },
        });

        expect(wrapper.findAll('line')).toHaveLength(0);
    });

    it('highlights a connection only when both ends are selected', () => {
        const wrapper = mount(TalentTreeGrid, {
            props: {
                nodes: [
                    makeNode(1, 0, 0, { selected_rank: 1, unlocks: [2, 3] }),
                    makeNode(2, 1, 0, { selected_rank: 1 }),
                    makeNode(3, 2, 0),
                ],
            },
        });

        const strokes = wrapper.findAll('line').map(line => line.attributes('stroke'));

        expect(strokes).toContain('var(--wp-accent)');
        expect(strokes).toContain('var(--wp-border-strong)');
    });

    it('draws connections between the centers of the two nodes', () => {
        setViewportWidth(1280);

        const wrapper = mount(TalentTreeGrid, {
            props: { nodes: [makeNode(1, 0, 0, { unlocks: [2] }), makeNode(2, 1, 0)] },
        });

        const line = wrapper.find('line');

        expect(line.attributes('x1')).toBe(String(PADDING + 30));
        expect(line.attributes('y1')).toBe(String(PADDING + 30));
        expect(line.attributes('x2')).toBe(String(PADDING + 74 + 30));
    });
});
