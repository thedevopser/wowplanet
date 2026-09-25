import { describe, it, expect, vi } from 'vitest';
import { ref } from 'vue';
import { LEGACY_PALETTE, mountWithPlugins } from '../../tests/helpers';
import ImportTrackingCard from './ImportTrackingCard.vue';
import ImportRunPanel from './ImportRunPanel.vue';

const tracking = (overrides = {}) => ({
    jobId: ref('job-1'),
    status: ref('running'),
    stageLabel: ref('Quêtes'),
    percent: ref(42),
    elapsedSeconds: ref(10),
    etaSeconds: ref(20),
    budget: ref({ used: 1, ceiling: 10 }),
    waiting: ref(null),
    steps: ref([]),
    lines: ref([]),
    interruptedFor: ref(null),
    abandonedIn: ref(null),
    steering: ref(null),
    error: ref(''),
    pause: vi.fn(),
    resume: vi.fn(),
    cancel: vi.fn(),
    ...overrides,
});

const mountCard = (state) => mountWithPlugins(ImportTrackingCard, { props: { tracking: state } });

describe('ImportTrackingCard', () => {
    it('shows nothing while no import is followed', async () => {
        const wrapper = await mountCard(tracking({ jobId: ref(null) }));

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('titles the follow-up of an import « Suivi »', async () => {
        const wrapper = await mountCard(tracking());

        expect(wrapper.get('section h2').text()).toBe('Suivi');
        expect(wrapper.get('section').attributes('aria-labelledby')).toBe(wrapper.get('h2').attributes('id'));
    });

    it('hands the state of the import to the run panel', async () => {
        const panel = (await mountCard(tracking())).getComponent(ImportRunPanel);

        expect(panel.props()).toMatchObject({ status: 'running', stageLabel: 'Quêtes', percent: 42, budget: { used: 1, ceiling: 10 } });
    });

    it('passes the orders given on the panel to the import', async () => {
        const state = tracking();
        const panel = (await mountCard(state)).getComponent(ImportRunPanel);

        panel.vm.$emit('pause');
        panel.vm.$emit('resume');
        panel.vm.$emit('cancel');

        expect(state.pause).toHaveBeenCalledOnce();
        expect(state.resume).toHaveBeenCalledOnce();
        expect(state.cancel).toHaveBeenCalledOnce();
    });

    it('reports an order the server refused as an alert', async () => {
        const wrapper = await mountCard(tracking({ error: ref('Pause refusée') }));

        expect(wrapper.get('[role="alert"]').text()).toBe('Pause refusée');
    });

    it('draws only with the tokens of the design system', async () => {
        expect((await mountCard(tracking({ error: ref('x') }))).html()).not.toMatch(LEGACY_PALETTE);
    });
});
