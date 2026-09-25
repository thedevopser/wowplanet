import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CategoryIcon from './CategoryIcon.vue';

const shapesOf = category => {
    const wrapper = mount(CategoryIcon, { props: { category } });

    return {
        paths: wrapper.findAll('path').length,
        polylines: wrapper.findAll('polyline').length,
        lines: wrapper.findAll('line').length,
        circles: wrapper.findAll('circle').length,
        polygons: wrapper.findAll('polygon').length,
    };
};

describe('CategoryIcon', () => {
    it('is hidden from assistive technology', () => {
        const wrapper = mount(CategoryIcon, { props: { category: 'quests' } });

        expect(wrapper.attributes('aria-hidden')).toBe('true');
    });

    it('draws a scroll for quests', () => {
        expect(shapesOf('quests')).toMatchObject({ paths: 1, polylines: 2, lines: 2 });
    });

    it('draws a trophy for achievements', () => {
        expect(shapesOf('achievements')).toMatchObject({ paths: 4, polygons: 0 });
    });

    it('draws a horseshoe for mounts', () => {
        expect(shapesOf('mounts')).toMatchObject({ paths: 1, polygons: 0 });
    });

    it('draws a paw for pets', () => {
        expect(shapesOf('pets')).toMatchObject({ circles: 5, paths: 1 });
    });

    it('draws a house for decor', () => {
        expect(shapesOf('decor')).toMatchObject({ paths: 1, polylines: 1 });
    });

    it('draws a tunic for both wardrobe categories', () => {
        expect(shapesOf('transmog')).toMatchObject({ paths: 1, polygons: 0 });
        expect(shapesOf('appearances')).toMatchObject({ paths: 1, polygons: 0 });
    });

    it('draws a hammer for professions', () => {
        expect(shapesOf('professions')).toMatchObject({ paths: 1, polylines: 0 });
    });

    it('falls back to a star for an unknown category', () => {
        expect(shapesOf('unknown')).toMatchObject({ polygons: 1, paths: 0 });
    });
});
