import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/inconnu', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../tests/helpers';
import AppLayout from '../layouts/AppLayout.vue';
import NotFoundPage from './NotFoundPage.vue';
import { expectNoAxeViolations } from '../tests/axe';

describe('NotFoundPage', () => {
    it('renders the error code and an explanation', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        expect(wrapper.text()).toContain('404');
        expect(wrapper.text()).toContain('Page introuvable');
    });

    it('offers a way back to the home page first, then to the database', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual(['/', '/base-de-donnees']);
    });

    it('titles the page with its single first-level heading', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        expect(wrapper.findAll('h1').map((heading) => heading.text())).toEqual(['Page introuvable']);
    });

    it('keeps the error code out of the reading order, as a decoration', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        expect(wrapper.find('[data-code]').attributes('aria-hidden')).toBe('true');
    });

    it('is rendered inside the application layout', () => {
        expect(NotFoundPage.layout).toBe(AppLayout);
    });

    it('shows no accessibility violation that axe can detect', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        await expectNoAxeViolations(wrapper.element);
    });
});
