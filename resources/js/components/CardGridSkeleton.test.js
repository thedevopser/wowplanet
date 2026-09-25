import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CardGridSkeleton from './CardGridSkeleton.vue';

const mountSkeleton = (props = {}) => mount(CardGridSkeleton, { props: { label: 'Chargement de vos personnages', ...props } });

describe('CardGridSkeleton', () => {
    it('announces what is loading to assistive technologies', () => {
        const wrapper = mountSkeleton();

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.find('.sr-only').text()).toBe('Chargement de vos personnages');
    });

    it('holds the room of six cards by default', () => {
        expect(mountSkeleton().findAll('[data-skeleton-card]')).toHaveLength(6);
    });

    it('holds the room of the number of cards asked for', () => {
        expect(mountSkeleton({ count: 3 }).findAll('[data-skeleton-card]')).toHaveLength(3);
    });

    it('rejects a count below one', () => {
        expect(CardGridSkeleton.props.count.validator(0)).toBe(false);
    });
});
