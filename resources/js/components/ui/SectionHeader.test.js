import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import SectionHeader from './SectionHeader.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('SectionHeader', () => {
    it('titles a section with a second-level heading by default', () => {
        const heading = mount(SectionHeader, { props: { title: 'Progression' } }).find('h2');

        expect(heading.text()).toBe('Progression');
    });

    it.each([2, 3, 4])('can title at level %i to keep the outline', (level) => {
        expect(mount(SectionHeader, { props: { title: 'Réputations', level } }).find(`h${level}`).exists()).toBe(true);
    });

    it('refuses a level that would break the outline', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mount(SectionHeader, { props: { title: 'Réputations', level: 1 } });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "level"');
        expect(wrapper.find('h2').exists()).toBe(true);
    });

    it('adds a description when given one', () => {
        const wrapper = mount(SectionHeader, { props: { title: 'Raids', description: 'Meilleure difficulté par raid' } });

        expect(wrapper.find('p').text()).toBe('Meilleure difficulté par raid');
        expect(wrapper.find('p').classes()).toContain('text-muted');
    });

    it('has neither description nor action area unless given', () => {
        const wrapper = mount(SectionHeader, { props: { title: 'Raids' } });

        expect(wrapper.find('p').exists()).toBe(false);
        expect(wrapper.find('[data-section-actions]').exists()).toBe(false);
    });

    it('places actions beside the title', () => {
        const wrapper = mount(SectionHeader, {
            props: { title: 'Raids' },
            slots: { actions: '<button>Filtrer</button>' },
        });

        expect(wrapper.find('[data-section-actions] button').text()).toBe('Filtrer');
    });
});
