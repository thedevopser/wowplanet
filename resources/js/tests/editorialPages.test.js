import { describe, it, expect, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from './helpers';
import CguPage from '../pages/CguPage.vue';
import FaqPage from '../pages/FaqPage.vue';
import PrivacyPage from '../pages/PrivacyPage.vue';

const meta = { title: 'T', description: 'D', canonicalUrl: '', ogType: '', ogTitle: '', ogDescription: '', ogImage: '', ogUrl: '' };

const RAW_PALETTE = /\b(?:text|bg|border|from|via|to)-(?:slate|gray|amber|blue|red|emerald|violet|cyan|indigo|rose)-\d{2,3}\b|#[0-9a-f]{6}\b/i;

describe.each([['CguPage', CguPage], ['FaqPage', FaqPage], ['PrivacyPage', PrivacyPage]])('%s', (name, page) => {
    it('points each entry of its table of contents to a section of the page', async () => {
        const wrapper = await mountWithPlugins(page, { props: { meta } });
        const targets = wrapper.findAll('nav[aria-label="Sommaire"] a').map((link) => link.attributes('href').slice(1));
        const sections = wrapper.findAll('article section').map((section) => section.attributes('id'));

        expect(targets.length).toBeGreaterThan(0);
        expect(targets).toEqual(sections);
    });
});

describe.each(['CguPage', 'FaqPage', 'PrivacyPage', 'AddonsPage'])('%s', (name) => {
    it('takes its colours from the tokens, never from a raw palette', () => {
        const source = readFileSync(resolve(__dirname, `../pages/${name}.vue`), 'utf8');

        expect(source).not.toMatch(RAW_PALETTE);
    });
});
