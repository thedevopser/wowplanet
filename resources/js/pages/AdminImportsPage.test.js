import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/imports', props: {} }),
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminImportsPage from './AdminImportsPage.vue';

vi.mock('axios');

const entities = [
    { stage: 'achievements', label: 'Hauts faits', rows: 8555, imported_at: '2026-09-18T20:00:00+00:00', build: '12.1.0_68914', estimated_api_calls: 8700 },
    { stage: 'quests', label: 'Quêtes', rows: 22055, imported_at: null, build: null, estimated_api_calls: 3000 },
    { stage: 'mounts', label: 'Montures', rows: 1663, imported_at: '2026-09-19T08:30:00+00:00', build: '12.1.0_68914', estimated_api_calls: 5 },
];

const progress = (overrides = {}) => ({
    data: {
        status: 'running',
        stage: 'quests',
        stage_label: 'Quêtes',
        percent: 10,
        elapsed_seconds: 3,
        eta_seconds: null,
        budget: { used: 10, ceiling: 30000 },
        waiting: null,
        steps: [],
        log: { cursor: 1, lines: ['[10:00:00] Import démarré.'] },
        ...overrides,
    },
});

const mountPage = async () => {
    axios.get = vi.fn().mockResolvedValue({ data: { jobId: null } });

    return mountWithPlugins(AdminImportsPage, { props: { entities } });
};

const buttonLabelled = (wrapper, label) => wrapper.findAll('button').find(b => b.text() === label);

const check = async (wrapper, index) => {
    await wrapper.findAll('input[type="checkbox"]')[index].setValue(true);
};

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
});

afterEach(() => vi.useRealTimers());

describe('AdminImportsPage', () => {
    it('lists the entities it was handed', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Hauts faits');
        expect(wrapper.text()).toContain('Montures');
    });

    it('asks whether an import is already running when it opens', async () => {
        await mountPage();

        expect(axios.get).toHaveBeenCalledWith('/api/admin/import/current');
    });

    it('picks the tracking of a running import back up', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: 'job-7' } })
            .mockResolvedValue(progress());

        const wrapper = await mountWithPlugins(AdminImportsPage, { props: { entities } });
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain('Import démarré.');
    });

    it('refuses to launch a selection while nothing is selected', async () => {
        const wrapper = await mountPage();

        expect(buttonLabelled(wrapper, 'Importer la sélection').attributes('disabled')).toBeDefined();
    });

    it('launches the selected entities in incremental mode', async () => {
        const wrapper = await mountPage();
        await check(wrapper, 1);
        await buttonLabelled(wrapper, 'Importer la sélection').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import', {
            scope: 'selection',
            stages: ['quests'],
            mode: 'incremental',
        });
    });

    it('launches everything without asking, in incremental mode', async () => {
        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import', { scope: 'all', stages: [], mode: 'incremental' });
    });

    it('spells out what each mode implies, not just its name', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Incrémental');
        expect(wrapper.text()).toContain('build');
        expect(wrapper.text()).toContain('Forcé');
    });

    it('announces the cost of a forced import on everything before launching it', async () => {
        const wrapper = await mountPage();
        await wrapper.find('input[value="forced"]').setValue(true);
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');

        expect(axios.post).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('appels');
    });

    it('launches the forced import on everything once confirmed', async () => {
        const wrapper = await mountPage();
        await wrapper.find('input[value="forced"]').setValue(true);
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await buttonLabelled(wrapper, 'Lancer quand même').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import', { scope: 'all', stages: [], mode: 'forced' });
    });

    it('drops the forced import on everything when the confirmation is declined', async () => {
        const wrapper = await mountPage();
        await wrapper.find('input[value="forced"]').setValue(true);
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await buttonLabelled(wrapper, 'Annuler').trigger('click');

        expect(axios.post).not.toHaveBeenCalled();
        expect(wrapper.text()).not.toContain('Lancer quand même');
    });

    it('launches a forced import on a selection without asking, the cost being bounded', async () => {
        const wrapper = await mountPage();
        await wrapper.find('input[value="forced"]').setValue(true);
        await check(wrapper, 2);
        await buttonLabelled(wrapper, 'Importer la sélection').trigger('click');

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import', {
            scope: 'selection',
            stages: ['mounts'],
            mode: 'forced',
        });
    });

    it('follows the import it just launched', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: null } })
            .mockResolvedValue(progress());

        const wrapper = await mountWithPlugins(AdminImportsPage, { props: { entities } });
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.get).toHaveBeenLastCalledWith('/api/admin/import/job-1?cursor=0');
        expect(wrapper.text()).toContain('Import démarré.');
    });

    it('says which import is already running when the server refuses a second one', async () => {
        axios.post = vi.fn().mockRejectedValue({
            response: { status: 409, data: { message: 'Un import est déjà en cours depuis 4 min 12 s.' } },
        });

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Un import est déjà en cours depuis 4 min 12 s.');
    });

    it('reports a launch that fails for any other reason', async () => {
        axios.post = vi.fn().mockRejectedValue(new Error('boom'));

        const wrapper = await mountPage();
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Erreur lors du lancement');
    });

    it('locks the launch buttons while an import runs', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: null } })
            .mockResolvedValue(progress());

        const wrapper = await mountWithPlugins(AdminImportsPage, { props: { entities } });
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(buttonLabelled(wrapper, 'Importer tout').attributes('disabled')).toBeDefined();
    });

    it('stops following once the page is left', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: null } })
            .mockResolvedValue(progress());

        const wrapper = await mountWithPlugins(AdminImportsPage, { props: { entities } });
        await buttonLabelled(wrapper, 'Importer tout').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        const callsBefore = axios.get.mock.calls.length;
        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(5000);

        expect(axios.get).toHaveBeenCalledTimes(callsBefore);
    });

    it('pauses the import it follows from the tracking panel', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: 'job-1' } })
            .mockResolvedValue(progress());
        axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });

        const wrapper = await mountWithPlugins(AdminImportsPage, { props: { entities } });
        await vi.advanceTimersByTimeAsync(0);

        await wrapper.get('[data-action="pause"]').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/import/job-1/pause');

        wrapper.unmount();
    });

    it('says why an order was refused instead of staying silent', async () => {
        axios.get = vi.fn()
            .mockResolvedValueOnce({ data: { jobId: 'job-1' } })
            .mockResolvedValue(progress());
        axios.post = vi.fn().mockRejectedValue({ response: { data: { message: "Cet import n'est plus celui qui tourne." } } });

        const wrapper = await mountWithPlugins(AdminImportsPage, { props: { entities } });
        await vi.advanceTimersByTimeAsync(0);

        await wrapper.get('[data-action="pause"]').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain("Cet import n'est plus celui qui tourne.");

        wrapper.unmount();
    });
});
