import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: {} }),
}));

import { mountWithPlugins } from '../../tests/helpers';
import BuildStatusBanner from './BuildStatusBanner.vue';

const upstream = (overrides = {}) => ({
    label: 'API Blizzard',
    build: '12.1.0_68914',
    checked_at: '2026-09-21T09:12:04+00:00',
    reachable: true,
    ...overrides,
});

const entry = (overrides = {}) => ({
    stage: 'mounts',
    label: 'Montures',
    upstream: 'blizzard',
    build: '12.1.0_68914',
    upstream_build: '12.1.0_68914',
    imported_at: '2026-09-19T19:38:50+00:00',
    state: 'current',
    note: null,
    ...overrides,
});

const status = (overrides = {}) => ({
    upstreams: {
        blizzard: upstream(),
        wago: upstream({ label: 'wago.tools', build: '12.1.0.69875' }),
    },
    entries: [entry({ stage: 'reference', label: 'Socle de référence', upstream: 'wago', build: '12.1.0.69875', upstream_build: '12.1.0.69875' }), entry()],
    behind: [],
    is_up_to_date: true,
    is_conclusive: true,
    ...overrides,
});

const mountBanner = (props = {}) => mountWithPlugins(BuildStatusBanner, {
    props: { status: status(), ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('BuildStatusBanner', () => {
    it('says everything is up to date rather than showing nothing', async () => {
        const wrapper = await mountBanner();

        expect(wrapper.get('[data-role="build-status"]').attributes('data-state')).toBe('up-to-date');
        expect(wrapper.text()).toContain('à jour');
    });

    it('shows the build each upstream serves', async () => {
        const wrapper = await mountBanner();

        expect(wrapper.get('[data-source="blizzard"]').text()).toContain('12.1.0_68914');
        expect(wrapper.get('[data-source="wago"]').text()).toContain('12.1.0.69875');
    });

    it('announces the entities left behind', async () => {
        const wrapper = await mountBanner({
            status: status({
                entries: [entry({ state: 'stale', build: '12.1.0_68000' })],
                behind: ['mounts'],
                is_up_to_date: false,
            }),
        });

        expect(wrapper.get('[data-role="build-status"]').attributes('data-state')).toBe('behind');
        expect(wrapper.get('[data-alert="stale"]').exists()).toBe(true);
    });

    it('lists the socle among the entities, not apart from them', async () => {
        const wrapper = await mountBanner();

        expect(wrapper.get('[data-stage="reference"]').exists()).toBe(true);
    });

    it('emits the stages the server listed when everything is updated at once', async () => {
        const wrapper = await mountBanner({
            status: status({ behind: ['reference', 'mounts'], is_up_to_date: false }),
        });

        await wrapper.get('[data-action="update-all"]').trigger('click');

        expect(wrapper.emitted('update')[0]).toEqual([['reference', 'mounts']]);
    });

    it('emits the one stage a row asks to update', async () => {
        const wrapper = await mountBanner({
            status: status({
                entries: [entry({ state: 'stale' })],
                behind: ['mounts'],
                is_up_to_date: false,
            }),
        });

        await wrapper.get('[data-action="update-stage"]').trigger('click');

        expect(wrapper.emitted('update')[0]).toEqual(['mounts']);
    });

    it('shows the last known build with the date it was read when an upstream is silent', async () => {
        const wrapper = await mountBanner({
            status: status({
                upstreams: {
                    blizzard: upstream({ reachable: false }),
                    wago: upstream({ label: 'wago.tools', build: '12.1.0.69875' }),
                },
                is_conclusive: false,
                is_up_to_date: false,
            }),
        });

        const line = wrapper.get('[data-source="blizzard"]');

        expect(line.get('[data-alert="unreachable"]').exists()).toBe(true);
        expect(line.text()).toContain('12.1.0_68914');
        expect(line.text()).toContain('21/09/2026');
    });

    it('never says everything is up to date when an upstream could not be read', async () => {
        const wrapper = await mountBanner({
            status: status({
                upstreams: {
                    blizzard: upstream({ reachable: false }),
                    wago: upstream({ label: 'wago.tools', build: '12.1.0.69875' }),
                },
                is_conclusive: false,
                is_up_to_date: false,
            }),
        });

        expect(wrapper.get('[data-role="build-status"]').attributes('data-state')).toBe('inconclusive');
        expect(wrapper.text()).not.toContain('Tout est à jour');
    });

    it('says an upstream was never reached rather than showing an empty build', async () => {
        const wrapper = await mountBanner({
            status: status({
                upstreams: {
                    blizzard: upstream({ build: null, checked_at: null, reachable: false }),
                    wago: upstream({ label: 'wago.tools', build: '12.1.0.69875' }),
                },
                is_conclusive: false,
                is_up_to_date: false,
            }),
        });

        expect(wrapper.get('[data-source="blizzard"]').text()).toContain('jamais');
    });

    it('dates each upstream reading, so a check that changes nothing is still visible', async () => {
        const wrapper = await mountBanner();

        expect(wrapper.get('[data-source="blizzard"]').text()).toContain('21/09/2026');
        expect(wrapper.get('[data-source="wago"]').text()).toContain('21/09/2026');
    });

    it('says it is checking while the call is still out', async () => {
        const wrapper = await mountBanner({ checking: true });

        const button = wrapper.get('[data-action="check"]');

        expect(button.text()).toContain('Vérification');
        expect(button.attributes('disabled')).toBeDefined();
    });

    it('asks for a fresh check on demand', async () => {
        const wrapper = await mountBanner();

        await wrapper.get('[data-action="check"]').trigger('click');

        expect(wrapper.emitted('check')).toHaveLength(1);
    });

    it('launches nothing while an import is already running', async () => {
        const wrapper = await mountBanner({
            status: status({ behind: ['mounts'], is_up_to_date: false }),
            disabled: true,
        });

        expect(wrapper.get('[data-action="update-all"]').attributes('disabled')).toBeDefined();
    });

    it('offers no bulk update when nothing is behind', async () => {
        const wrapper = await mountBanner();

        expect(wrapper.find('[data-action="update-all"]').exists()).toBe(false);
    });
});
