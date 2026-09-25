import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { dimensionColor } from '../../utils/wowColors';
import ExpansionFilter from './ExpansionFilter.vue';

const EXPANSIONS = [
    { id: 9, name: 'Dragonflight' },
    { id: 10, name: 'The War Within' },
    { id: 99, name: 'Non classé', onlyWhenFilled: true },
];

const COLLECTIONS = {
    9: { quests: { completed: 50, total: 1200 } },
    10: { quests: { completed: 5, total: 20 } },
    99: { quests: { completed: 0, total: 0 } },
};

const mountFilter = (props = {}) => mount(ExpansionFilter, {
    props: { expansions: EXPANSIONS, collections: COLLECTIONS, collectionType: 'quests', modelValue: 10, ...props },
    global: { stubs: { Select: true } },
});

const select = (wrapper) => wrapper.findComponent({ name: 'Select' });

describe('ExpansionFilter', () => {
    it('is a labelled list, not a row of tabs', () => {
        const wrapper = mountFilter();

        expect(wrapper.find('[role="tab"]').exists()).toBe(false);
        expect(select(wrapper).props('label')).toBe('Extension');
    });

    it('lists the expansions, most recent first, with their progress in the colour of the dimension', () => {
        const options = select(mountFilter()).props('options');

        expect(options.map((option) => [option.value, option.label, option.hint.replace(/\s/g, ' ')])).toEqual([
            ['10', 'The War Within', '5 / 20'],
            ['9', 'Dragonflight', '50 / 1 200'],
        ]);
        expect(options[0].progress).toEqual({ value: 5, max: 20, color: dimensionColor('quests').base });
    });

    it('shows a bucket only where it holds something', () => {
        const wrapper = mountFilter({ collections: { ...COLLECTIONS, 99: { quests: { completed: 1, total: 3 } } } });

        expect(select(wrapper).props('options').map((option) => option.label)).toContain('Non classé');
    });

    it('selects the current expansion and reports a new one as a number', async () => {
        const wrapper = mountFilter();

        expect(select(wrapper).props('modelValue')).toBe('10');

        await select(wrapper).vm.$emit('update:modelValue', '9');

        expect(wrapper.emitted('update:modelValue')).toEqual([[9]]);
    });

    it('colours the recipes of the professions after their dimension', () => {
        const wrapper = mountFilter({ collectionType: 'recipes', collections: { 10: { recipes: { completed: 1, total: 2 } } } });

        expect(select(wrapper).props('options')[0].progress.color).toBe(dimensionColor('professions').base);
    });
});
