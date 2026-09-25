import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EditorialPage from './EditorialPage.vue';

const mountPage = (props = {}, slots = {}) => mount(EditorialPage, {
    props: { title: 'Politique de confidentialité', ...props },
    slots: { default: '<section><h2>1. Responsable</h2><p>Texte</p></section>', ...slots },
});

describe('EditorialPage', () => {
    it('titles the page with its single first-level heading', () => {
        expect(mountPage().findAll('h1').map((heading) => heading.text())).toEqual(['Politique de confidentialité']);
    });

    it('introduces the page with a lead when given one', () => {
        expect(mountPage({ lead: 'Tout savoir.' }).find('header p').text()).toBe('Tout savoir.');
        expect(mountPage().find('header p').exists()).toBe(false);
    });

    it('dates the last update in a machine-readable form', () => {
        const time = mountPage({ updatedAt: '2026-09-25' }).find('header time');

        expect(time.attributes('datetime')).toBe('2026-09-25');
        expect(time.text()).toBe('25 septembre 2026');
    });

    it('keeps the text to a reading width and styles it from the tokens', () => {
        const wrapper = mountPage();

        expect(wrapper.find('article.editorial').classes()).toContain('max-w-3xl');
        expect(mountPage({ sections: [{ id: 'objet', title: 'Objet' }] }).find('article.editorial').classes()).not.toContain('max-w-3xl');
        expect(wrapper.find('.editorial h2').text()).toBe('1. Responsable');
    });

    it('lets a wider block run beside the text', () => {
        const wrapper = mountPage({}, { wide: '<ul data-cards><li>MapTidy</li></ul>' });

        expect(wrapper.find('[data-cards]').exists()).toBe(true);
    });

    const sections = [{ id: 'objet', title: 'Objet' }, { id: 'donnees', title: 'Données collectées' }];

    it('opens with a header card, like the other pages of the site', () => {
        expect(mountPage().find('header').classes()).toEqual(expect.arrayContaining(['bg-surface', 'border-l-4']));
    });

    it('lists its sections in a table of contents that links to each of them', () => {
        const nav = mountPage({ sections }).find('nav[aria-label="Sommaire"]');

        expect(nav.findAll('a').map((link) => [link.attributes('href'), link.text()])).toEqual([
            ['#objet', 'Objet'],
            ['#donnees', 'Données collectées'],
        ]);
    });

    it('folds the table of contents on small screens', () => {
        const wrapper = mountPage({ sections });

        expect(wrapper.find('details summary').text()).toBe('Sommaire');
        expect(wrapper.find('details').findAll('a')).toHaveLength(2);
    });

    it('marks the first section as the one being read before any scroll', () => {
        const current = mountPage({ sections }).findAll('nav[aria-label="Sommaire"] [aria-current="location"]');

        expect(current.map((link) => link.attributes('href'))).toEqual(['#objet']);
    });

    it('needs no table of contents for a page without sections', () => {
        const wrapper = mountPage();

        expect(wrapper.find('nav').exists()).toBe(false);
        expect(wrapper.find('details').exists()).toBe(false);
    });

    it('leaves the text column out when the page has only wide content', () => {
        const wrapper = mount(EditorialPage, { props: { title: 'Nos Addons' }, slots: { wide: '<ul data-cards></ul>' } });

        expect(wrapper.find('article').exists()).toBe(false);
    });
});
