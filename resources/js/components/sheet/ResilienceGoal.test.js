import { describe, it, expect } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ResilienceGoal from './ResilienceGoal.vue';
import Select from '../ui/Select.vue';
import { expectNoAxeViolations } from '../../tests/axe';
import { LEGACY_PALETTE, mountWithPlugins } from '../../tests/helpers';

const DUNGEONS = [
    { dungeon_id: 249, name: 'Repos des rois', best_timed_level: 13 },
    { dungeon_id: 250, name: 'Temple de Sephraliss', best_timed_level: 12 },
    { dungeon_id: 399, name: 'Bassins de l’Essence rubis', best_timed_level: 11 },
    { dungeon_id: 584, name: 'Le val Aveuglant', best_timed_level: null },
];

function makeResilience(level = null) {
    return {
        level,
        min_level: 12,
        max_level: 25,
        dungeons: DUNGEONS,
        targets: Array.from({ length: 14 }, (_, index) => ({
            level: 12 + index,
            remaining: DUNGEONS.filter((dungeon) => (dungeon.best_timed_level ?? 0) < 12 + index).map((dungeon) => dungeon.dungeon_id),
        })),
    };
}

const mountGoal = (resilience) => mountWithPlugins(ResilienceGoal, { props: { resilience } });

const remaining = (wrapper) => wrapper.findAll('[data-remaining]').map((entry) => entry.text());

async function aimAt(wrapper, level) {
    wrapper.findComponent(Select).vm.$emit('update:modelValue', String(level));
    await flushPromises();
}

describe('ResilienceGoal', () => {
    it('offers every level from the lowest to the ceiling, under a visible label', async () => {
        const wrapper = await mountGoal(makeResilience());
        const select = wrapper.findComponent(Select);

        expect(wrapper.find('h3').text()).toBe('Objectif rési');
        expect(select.props('label')).toBe('Niveau visé');
        expect(select.props('options').map((option) => option.label)).toEqual(Array.from({ length: 14 }, (_, index) => `+${12 + index}`));
    });

    it('aims at +12 for a character without resilience and lists the dungeons left with their best run in time', async () => {
        const wrapper = await mountGoal(makeResilience());

        expect(wrapper.findComponent(Select).props('modelValue')).toBe('12');
        expect(wrapper.find('[data-goal-count]').text()).toBe('2 donjons restants sur 4');
        expect(remaining(wrapper)).toEqual(['Bassins de l’Essence rubisMeilleure clé timée : +11', 'Le val AveuglantJamais timé']);
    });

    it('aims one level above the resilience reached', async () => {
        const resilience = makeResilience(12);
        resilience.targets[0].remaining = [];
        const wrapper = await mountGoal(resilience);

        expect(wrapper.findComponent(Select).props('modelValue')).toBe('13');
    });

    it('updates the list when another level is chosen', async () => {
        const wrapper = await mountGoal(makeResilience());

        await aimAt(wrapper, 14);

        expect(wrapper.find('[data-goal-count]').text()).toBe('4 donjons restants sur 4');
        expect(remaining(wrapper)).toHaveLength(4);
    });

    it('aims again from the resilience of another character', async () => {
        const wrapper = await mountGoal(makeResilience());
        await aimAt(wrapper, 20);

        await wrapper.setProps({ resilience: makeResilience(15) });

        expect(wrapper.findComponent(Select).props('modelValue')).toBe('16');
    });

    it('marks in the list of levels the ones already reached', async () => {
        const resilience = makeResilience(12);
        resilience.targets[0].remaining = [];
        resilience.targets[1].remaining = [584];
        const wrapper = await mountGoal(resilience);

        expect(wrapper.findComponent(Select).props('options').slice(0, 2).map((option) => option.hint)).toEqual(['Atteinte', '1 restant']);
    });

    it('writes a single dungeon left in the singular', async () => {
        const resilience = makeResilience();
        resilience.targets[0].remaining = [584];
        const wrapper = await mountGoal(resilience);

        expect(wrapper.find('[data-goal-count]').text()).toBe('1 donjon restant sur 4');
    });

    it('says the level is reached instead of an empty list', async () => {
        const resilience = makeResilience(25);
        resilience.targets.forEach((target) => { target.remaining = []; });
        const wrapper = await mountGoal(resilience);

        expect(wrapper.findComponent(Select).props('modelValue')).toBe('25');
        expect(wrapper.find('[data-goal-reached]').text()).toBe('Rési +25 atteinte : les 4 donjons sont timés à ce niveau ou au-dessus.');
        expect(wrapper.find('[data-goal-count]').exists()).toBe(false);
        expect(remaining(wrapper)).toEqual([]);
    });

    it('tells for each level how many dungeons are left', async () => {
        const wrapper = await mountGoal(makeResilience());
        const hints = wrapper.findComponent(Select).props('options').map((option) => option.hint);

        expect(hints.slice(0, 3)).toEqual(['2 restants', '3 restants', '4 restants']);
    });

    it('has no accessibility violation, with dungeons left or the level reached', async () => {
        const left = await mountWithPlugins(ResilienceGoal, { props: { resilience: makeResilience() }, attachTo: document.body });
        await expectNoAxeViolations(left.element);
        left.unmount();

        const reached = makeResilience(25);
        reached.targets.forEach((target) => { target.remaining = []; });
        const done = await mountWithPlugins(ResilienceGoal, { props: { resilience: reached }, attachTo: document.body });
        await expectNoAxeViolations(done.element);
        done.unmount();
    });

    it('only uses design tokens', async () => {
        const wrapper = await mountGoal(makeResilience());

        expect(wrapper.html()).not.toMatch(LEGACY_PALETTE);
    });
});
