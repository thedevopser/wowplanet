import { describe, it, expect, vi, beforeEach } from 'vitest';
import { LEGACY_PALETTE, mountWithPlugins } from '../../tests/helpers';
import ImportRunPanel from './ImportRunPanel.vue';

const run = (overrides = {}) => ({
    status: 'running',
    stageLabel: 'Quêtes',
    percent: 42,
    elapsedSeconds: 125,
    etaSeconds: 180,
    budget: { used: 3200, ceiling: 30000 },
    waiting: null,
    steps: [
        { stage: 'quests', label: 'Quêtes', status: 'running', percent: 42, created: 0, updated: 0, deleted: 0, api_calls: 900, duration_ms: 4200, error: null },
        { stage: 'mounts', label: 'Montures', status: 'pending', percent: 0, created: 0, updated: 0, deleted: 0, api_calls: 0, duration_ms: 0, error: null },
    ],
    lines: ['[10:00:01] Import démarré.', '[10:00:02] Quêtes — démarrage.'],
    ...overrides,
});

const mountPanel = (props = {}) => mountWithPlugins(ImportRunPanel, { props: { ...run(), ...props } });

beforeEach(() => vi.clearAllMocks());

describe('ImportRunPanel', () => {
    it('names the entity being imported and how far it is', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('42');
    });

    it('shows the elapsed time and the estimate of what is left', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.text()).toContain('2 min 05 s');
        expect(wrapper.text()).toContain('3 min 00 s');
    });

    it('shows the Blizzard quota consumed against its ceiling', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.text()).toMatch(/3\s200/);
        expect(wrapper.text()).toMatch(/30\s000/);
    });

    it('says why the import waits, instead of looking stuck', async () => {
        const wrapper = await mountPanel({
            waiting: { reason: 'hourly_budget', seconds: 240, message: 'plafond horaire atteint, reprise dans 240 s' },
        });

        expect(wrapper.text()).toContain('En attente');
        expect(wrapper.text()).toContain('plafond horaire atteint, reprise dans 240 s');
    });

    it('shows nothing about waiting when the import is not waiting', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.text()).not.toContain('En attente');
    });

    it('lists the journal as it scrolls', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.text()).toContain('Quêtes — démarrage.');
    });

    it('tells a finished entity from a failed one in the report', async () => {
        const wrapper = await mountPanel({
            status: 'failed',
            steps: [
                { stage: 'quests', label: 'Quêtes', status: 'completed', percent: 100, created: 12, updated: 3, deleted: 0, api_calls: 900, duration_ms: 4200, error: null },
                { stage: 'mounts', label: 'Montures', status: 'failed', percent: 0, created: 0, updated: 0, deleted: 0, api_calls: 0, duration_ms: 0, error: 'index injoignable' },
            ],
        });

        expect(wrapper.text()).toContain('index injoignable');
        expect(wrapper.find('[data-status="completed"]').exists()).toBe(true);
        expect(wrapper.find('[data-status="failed"]').exists()).toBe(true);
    });

    it('says an entity was skipped rather than leaving it blank', async () => {
        const wrapper = await mountPanel({
            steps: [{ stage: 'quests', label: 'Quêtes', status: 'skipped', percent: 100, created: 0, updated: 0, deleted: 0, api_calls: 0, duration_ms: 0, error: null }],
        });

        expect(wrapper.text()).toContain('déjà à jour');
    });

    it('reports the rows an entity wrote once it is done', async () => {
        const wrapper = await mountPanel({
            steps: [{ stage: 'quests', label: 'Quêtes', status: 'completed', percent: 100, created: 12, updated: 3, deleted: 1, api_calls: 900, duration_ms: 4200, error: null }],
        });

        expect(wrapper.text()).toContain('12 créées');
        expect(wrapper.text()).toContain('3 mises à jour');
        expect(wrapper.text()).toContain('1 supprimée');
    });

    it('offers to pause an import that is running', async () => {
        const wrapper = await mountPanel();

        await wrapper.get('[data-action="pause"]').trigger('click');

        expect(wrapper.emitted('pause')).toHaveLength(1);
    });

    it('offers to resume a paused import, and says since when it waits', async () => {
        const wrapper = await mountPanel({ status: 'paused', interruptedFor: 125, abandonedIn: 3475 });

        expect(wrapper.text()).toContain('en pause');
        expect(wrapper.text()).toContain('2 min 05 s');
        expect(wrapper.find('[data-action="pause"]').exists()).toBe(false);

        await wrapper.get('[data-action="resume"]').trigger('click');

        expect(wrapper.emitted('resume')).toHaveLength(1);
    });

    it('warns how long a forgotten pause has before it is abandoned', async () => {
        const wrapper = await mountPanel({ status: 'paused', interruptedFor: 3480, abandonedIn: 120 });

        expect(wrapper.text()).toContain('abandonné');
        expect(wrapper.text()).toContain('2 min 00 s');
    });

    it('asks to confirm a cancellation, saying what it gives up', async () => {
        const wrapper = await mountPanel();

        await wrapper.get('[data-action="cancel"]').trigger('click');

        expect(wrapper.emitted('cancel')).toBeUndefined();
        expect(wrapper.text()).toContain('abandonnée');
        expect(wrapper.text()).toContain('conservent leur résultat');
    });

    it('cancels only once the confirmation is given', async () => {
        const wrapper = await mountPanel();

        await wrapper.get('[data-action="cancel"]').trigger('click');
        await wrapper.get('[data-action="confirm-cancel"]').trigger('click');

        expect(wrapper.emitted('cancel')).toHaveLength(1);
    });

    it('drops the cancellation when it is called off', async () => {
        const wrapper = await mountPanel();

        await wrapper.get('[data-action="cancel"]').trigger('click');
        await wrapper.get('[data-action="keep-going"]').trigger('click');

        expect(wrapper.emitted('cancel')).toBeUndefined();
        expect(wrapper.find('[data-action="confirm-cancel"]').exists()).toBe(false);
    });

    it('says an order is waiting to be acted on, rather than claiming it is done', async () => {
        const wrapper = await mountPanel({ steering: 'pause' });

        expect(wrapper.text()).toContain('Pause demandée');
        expect(wrapper.get('[data-action="cancel"]').attributes('disabled')).toBeDefined();
    });

    it('offers nothing to steer once the import is over', async () => {
        const wrapper = await mountPanel({ status: 'completed' });

        expect(wrapper.find('[data-action="pause"]').exists()).toBe(false);
        expect(wrapper.find('[data-action="resume"]').exists()).toBe(false);
        expect(wrapper.find('[data-action="cancel"]').exists()).toBe(false);
    });

    it('says an interrupted import was interrupted, never that it finished', async () => {
        const wrapper = await mountPanel({ status: 'cancelled' });

        expect(wrapper.text()).toContain('interrompu');
        expect(wrapper.text()).not.toContain('Import terminé');
    });

    it('draws its progress as a named progress bar', async () => {
        const bar = (await mountPanel()).get('[role="progressbar"]');

        expect(bar.attributes('aria-valuenow')).toBe('42');
        expect(bar.attributes('aria-label')).toBe('Avancement de l’import');
    });

    // The percentage moves every second: announcing it would drown a screen reader.
    it('announces the entity and the state of the import, not every step of its percentage', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.get('[role="status"]').text()).toBe('En cours — Quêtes');
        await wrapper.setProps({ percent: 43 });
        expect(wrapper.get('[role="status"]').text()).toBe('En cours — Quêtes');
        await wrapper.setProps({ status: 'completed' });
        expect(wrapper.get('[role="status"]').text()).toBe('Import terminé');
    });

    it('gives the cancellation the tone of a destructive action', async () => {
        const wrapper = await mountPanel();

        expect(wrapper.get('[data-action="cancel"]').classes()).toContain('text-danger');
    });

    it('draws only with the tokens of the design system', async () => {
        const wrapper = await mountPanel({ waiting: { message: 'quota' } });
        await wrapper.get('[data-action="cancel"]').trigger('click');

        expect(wrapper.html()).not.toMatch(LEGACY_PALETTE);
        expect((await mountPanel({ status: 'paused', interruptedFor: 30 })).html()).not.toMatch(LEGACY_PALETTE);
    });
});
