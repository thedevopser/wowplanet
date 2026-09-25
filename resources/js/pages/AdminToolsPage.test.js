import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin/tools', props: {} }),
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminToolsPage from './AdminToolsPage.vue';

vi.mock('axios');

const mountTools = async (maintenance = false) => {
    axios.get = vi.fn().mockResolvedValue({ data: { maintenance } });

    return mountWithPlugins(AdminToolsPage);
};

const buttonLabelled = (wrapper, label) => wrapper.findAll('button').find(b => b.text() === label);

beforeEach(() => vi.clearAllMocks());

describe('AdminToolsPage', () => {
    it('fetches the maintenance status on mount', async () => {
        await mountTools();

        expect(axios.get).toHaveBeenCalledWith('/api/admin/status');
    });

    it('keeps working when the status request fails', async () => {
        axios.get = vi.fn().mockRejectedValue(new Error('boom'));

        const wrapper = await mountWithPlugins(AdminToolsPage);

        expect(wrapper.text()).toContain('Application en ligne');
    });

    it('hosts the discord composer', async () => {
        const wrapper = await mountTools();

        expect(wrapper.text()).toContain('Message Discord');
    });

    describe('maintenance', () => {
        it('reports the application as online', async () => {
            const wrapper = await mountTools(false);

            expect(wrapper.text()).toContain('Application en ligne');
            expect(buttonLabelled(wrapper, 'Activer la maintenance')).toBeDefined();
        });

        it('reports the maintenance mode as active', async () => {
            const wrapper = await mountTools(true);

            expect(wrapper.text()).toContain('Mode maintenance actif');
            expect(buttonLabelled(wrapper, 'Désactiver la maintenance')).toBeDefined();
        });

        it('enables the maintenance mode with a generated secret', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { maintenance: true } });

            const wrapper = await mountTools(false);
            await buttonLabelled(wrapper, 'Activer la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            const [url, payload] = axios.post.mock.calls[0];

            expect(url).toBe('/api/admin/maintenance');
            expect(payload.enable).toBe(true);
            expect(payload.secret).toMatch(/^[a-z0-9]{32}$/);
            expect(wrapper.text()).toContain('Mode maintenance actif');
        });

        it('shows the bypass url once maintenance is enabled', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { maintenance: true } });

            const wrapper = await mountTools(false);
            await buttonLabelled(wrapper, 'Activer la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            const secret = axios.post.mock.calls[0][1].secret;

            expect(wrapper.find('a[href$="' + secret + '"]').exists()).toBe(true);
        });

        it('disables the maintenance mode and drops the bypass url', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { maintenance: false } });

            const wrapper = await mountTools(true);
            await buttonLabelled(wrapper, 'Désactiver la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post).toHaveBeenCalledWith('/api/admin/maintenance', { enable: false, secret: null });
            expect(wrapper.text()).toContain('Application en ligne');
            expect(wrapper.text()).not.toContain('URL de bypass');
        });

        it('leaves the state untouched when the request fails', async () => {
            axios.post = vi.fn().mockRejectedValue(new Error('boom'));

            const wrapper = await mountTools(false);
            await buttonLabelled(wrapper, 'Activer la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Application en ligne');
        });
    });

    describe('cache', () => {
        it('shows the output of a successful purge', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { output: 'Cache vidé' } });

            const wrapper = await mountTools();
            await buttonLabelled(wrapper, 'Vider les caches').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post).toHaveBeenCalledWith('/api/admin/clear-cache');
            expect(wrapper.text()).toContain('Cache vidé');
        });

        it('shows the server message when the purge fails', async () => {
            axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Accès refusé' } } });

            const wrapper = await mountTools();
            await buttonLabelled(wrapper, 'Vider les caches').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Accès refusé');
        });

        it('falls back to a generic message when the failure carries none', async () => {
            axios.post = vi.fn().mockRejectedValue(new Error('boom'));

            const wrapper = await mountTools();
            await buttonLabelled(wrapper, 'Vider les caches').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Erreur');
        });
    });
});
