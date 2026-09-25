import { describe, it, expect, beforeEach } from 'vitest';

const page = vi.hoisted(() => ({ url: '/admin', props: {} }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => page,
}));

import { LEGACY_PALETTE, mountWithPlugins } from '../tests/helpers';
import AdminLayout from './AdminLayout.vue';

const mountLayout = async (url = '/admin') => {
    page.url = url;

    return mountWithPlugins(AdminLayout, { slots: { default: '<p>Contenu de la page</p>' } });
};

const activeLink = wrapper => wrapper.findAll('a').find(a => a.attributes('aria-current') === 'page');

beforeEach(() => vi.clearAllMocks());

describe('AdminLayout', () => {
    it('links to the seven sections of the panel', async () => {
        const wrapper = await mountLayout();

        const targets = wrapper.findAll('nav a').map(a => a.attributes('href'));

        expect(targets).toEqual(['/admin', '/admin/imports', '/admin/history', '/admin/reference', '/admin/taxonomy', '/admin/health', '/admin/tools']);
    });

    it('renders the page inside the layout', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.text()).toContain('Contenu de la page');
    });

    it.each([
        ['/admin', 'Tableau de bord'],
        ['/admin/imports', 'Imports'],
        ['/admin/history', 'Historique'],
        ['/admin/history/job-1', 'Historique'],
        ['/admin/reference', 'Socle de référence'],
        ['/admin/taxonomy', 'Taxonomie'],
        ['/admin/health', 'Santé'],
        ['/admin/tools', 'Outils'],
    ])('marks the tab of %s as the current one', async (url, label) => {
        const wrapper = await mountLayout(url);

        expect(activeLink(wrapper).text()).toBe(label);
    });

    it('keeps the imports tab current on a sub-route of the section', async () => {
        const wrapper = await mountLayout('/admin/imports/job-1');

        expect(activeLink(wrapper).text()).toBe('Imports');
    });

    it('leaves the dashboard tab alone on a sub-route of another section', async () => {
        const wrapper = await mountLayout('/admin/reference');

        expect(activeLink(wrapper).text()).not.toBe('Tableau de bord');
    });

    it('ignores the query string when deducing the current tab', async () => {
        const wrapper = await mountLayout('/admin/health?refresh=1');

        expect(activeLink(wrapper).text()).toBe('Santé');
    });

    it('names its navigation after the panel', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.find('nav').attributes('aria-label')).toBe('Administration');
    });

    it('marks the current tab with the accent and leaves the others muted', async () => {
        const wrapper = await mountLayout('/admin/health');
        const others = wrapper.findAll('nav a').filter((a) => a.attributes('aria-current') !== 'page');

        expect(activeLink(wrapper).classes()).toContain('text-accent');
        others.forEach((a) => expect(a.classes()).toContain('text-muted'));
    });

    it('gives every tab a 44 px target', async () => {
        const wrapper = await mountLayout();

        wrapper.findAll('nav a').forEach((a) => expect(a.classes()).toContain('min-h-11'));
    });

    it('draws only with the tokens of the design system', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.html()).not.toMatch(LEGACY_PALETTE);
    });
});
