import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CollectionIcon from './CollectionIcon.vue';

describe('CollectionIcon', () => {
    it('renders img when src is provided', () => {
        const wrapper = mount(CollectionIcon, {
            props: { src: 'https://example.com/icon.jpg', alt: 'Test', fallback: 'M' },
        });

        expect(wrapper.find('img').exists()).toBe(true);
        expect(wrapper.find('img').attributes('src')).toBe('https://example.com/icon.jpg');
    });

    it('renders fallback when src is null', () => {
        const wrapper = mount(CollectionIcon, {
            props: { src: null, fallback: 'M' },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toBe('M');
    });

    it('renders fallback on image error', async () => {
        const wrapper = mount(CollectionIcon, {
            props: { src: 'https://example.com/broken.jpg', fallback: 'P' },
        });

        expect(wrapper.find('img').exists()).toBe(true);

        await wrapper.find('img').trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toBe('P');
    });

    it('uses small size by default', () => {
        const wrapper = mount(CollectionIcon, {
            props: { src: null, fallback: 'D' },
        });

        expect(wrapper.find('.size-8').exists()).toBe(true);
    });

    it('uses large size when specified', () => {
        const wrapper = mount(CollectionIcon, {
            props: { src: null, fallback: 'D', size: 'lg' },
        });

        expect(wrapper.find('.size-10').exists()).toBe(true);
    });
});

describe('CollectionIcon tokens', () => {
    it('uses no raw palette class nor arbitrary size', () => {
        const html = mount(CollectionIcon, { props: { src: null, fallback: 'M' } }).html();

        expect(html).not.toMatch(/\b(?:bg|text|border)-(?:slate|white)(?:\/\d+|-\d+)?\b/);
        expect(html).not.toMatch(/text-\[\d+px\]/);
    });
});
