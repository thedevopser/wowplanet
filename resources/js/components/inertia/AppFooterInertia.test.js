import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import AppFooterInertia from './AppFooterInertia.vue';

const RAW_TEXT_SIZE = /text-\[\d+px\]/;

describe('AppFooterInertia', () => {
    it('states the site is unaffiliated with Blizzard', () => {
        const wrapper = mount(AppFooterInertia);

        expect(wrapper.text()).toContain('Site fan non officiel, sans lien ni affiliation avec Blizzard Entertainment.');
    });

    it('shows the current year in the copyright notice', () => {
        const wrapper = mount(AppFooterInertia);

        expect(wrapper.text()).toContain(String(new Date().getFullYear()));
    });

    it('opens a column to explore the main sections', () => {
        const wrapper = mount(AppFooterInertia);
        const explore = wrapper.find('nav[aria-labelledby="footer-explore"]');

        expect(wrapper.find('#footer-explore').text()).toBe('Explorer');
        expect(explore.findAll('a').map((a) => a.attributes('href'))).toEqual(['/base-de-donnees', '/classements-pvp', '/mon-compte']);
    });

    it('links to the legal and help pages', () => {
        const wrapper = mount(AppFooterInertia);
        const about = wrapper.find('nav[aria-labelledby="footer-about"]');

        expect(about.findAll('a').map((a) => a.attributes('href'))).toEqual(expect.arrayContaining(['/faq', '/addons', '/privacy', '/cgu']));
    });

    it('opens the Discord invitation in a safe new tab', () => {
        const wrapper = mount(AppFooterInertia);

        const discord = wrapper.findAll('a').find((a) => a.attributes('href').includes('discord.gg'));

        expect(discord.attributes('target')).toBe('_blank');
        expect(discord.attributes('rel')).toBe('noopener noreferrer');
    });

    it('adds no heading to the outline of the page', () => {
        expect(mount(AppFooterInertia).find('h1, h2, h3, h4').exists()).toBe(false);
    });

    it('keeps its text at twelve pixels at least', () => {
        expect(mount(AppFooterInertia).html()).not.toMatch(RAW_TEXT_SIZE);
    });
});
