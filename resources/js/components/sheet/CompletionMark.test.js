import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CompletionMark from './CompletionMark.vue';

describe('CompletionMark', () => {
    it('says a done entry is done', () => {
        const wrapper = mount(CompletionMark, { props: { done: true } });

        expect(wrapper.text()).toBe('Fait');
        expect(wrapper.find('.sr-only').exists()).toBe(true);
        expect(wrapper.classes()).toContain('text-success');
    });

    it('names the character who did it elsewhere, visibly', () => {
        const wrapper = mount(CompletionMark, { props: { done: false, elsewhere: true, owner: 'Jaina' } });

        expect(wrapper.text()).toBe('Fait par Jaina');
        expect(wrapper.classes()).toContain('text-warning');
    });

    it('falls back to another character without a name', () => {
        expect(mount(CompletionMark, { props: { done: false, elsewhere: true } }).text()).toBe('Fait par un autre personnage');
    });

    it('says what is left to do', () => {
        const wrapper = mount(CompletionMark, { props: { done: false } });

        expect(wrapper.text()).toBe('À faire');
        expect(wrapper.classes()).toContain('text-subtle');
    });

    it('prefers done over done elsewhere', () => {
        expect(mount(CompletionMark, { props: { done: true, elsewhere: true, owner: 'Jaina' } }).text()).toBe('Fait');
    });
});
