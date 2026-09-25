import { describe, it, expect, vi, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div data-head><slot /></div>' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/mon-compte', props: {} }),
    router: { push: vi.fn(), on: vi.fn(), visit: vi.fn() },
}));

import { router } from '@inertiajs/vue3';
import { mountWithPlugins } from '../tests/helpers';
import AccountPage from './AccountPage.vue';
import { expectNoAxeViolations } from '../tests/axe';

let wrapper;

async function mountPage(view = 'personnages') {
    wrapper = await mountWithPlugins(AccountPage, {
        props: { view },
        stubs: { CharactersView: true, ScoreView: true, ClassesView: true },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const tabs = () => wrapper.findAll('[role="tab"]');
const selected = () => tabs().find((tab) => tab.attributes('aria-selected') === 'true');

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('AccountPage', () => {
    it('titles the hub « Mon compte »', async () => {
        await mountPage();

        expect(wrapper.find('h1').text()).toBe('Mon compte');
    });

    it('offers its three views as tabs', async () => {
        await mountPage();

        expect(wrapper.find('[role="tablist"]').attributes('aria-label')).toBe('Vues du compte');
        expect(tabs().map((tab) => tab.text())).toEqual(['Personnages', 'Score du compte', 'Classes']);
    });

    it.each([
        ['personnages', 'Personnages', 'CharactersView', 'Mes personnages - WowPlanet'],
        ['score', 'Score du compte', 'ScoreView', 'Score du compte - WowPlanet'],
        ['classes', 'Classes', 'ClassesView', 'Mes classes - WowPlanet'],
    ])('opens the %s view given by the address', async (view, label, component, title) => {
        await mountPage(view);

        expect(selected().text()).toBe(label);
        expect(wrapper.findComponent({ name: component }).exists()).toBe(true);
        expect(wrapper.find('[data-head] title').text()).toBe(title);
    });

    it('writes a chosen view in the address and the history, without a request', async () => {
        await mountPage();

        await tabs()[1].trigger('mousedown', { button: 0 });

        const visit = router.push.mock.calls.at(-1)[0];
        expect(visit.url).toBe('/mon-compte/score');
        expect(visit.props({ view: 'personnages' })).toEqual({ view: 'score' });
    });

    it('comes back to the base address for the characters', async () => {
        await mountPage('classes');

        await tabs()[0].trigger('mousedown', { button: 0 });

        expect(router.push.mock.calls.at(-1)[0].url).toBe('/mon-compte');
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountPage();

        await expectNoAxeViolations(wrapper.element);
    });
});
