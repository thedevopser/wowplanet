import { describe, it, expect, vi, afterEach } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { useScrollSpy } from './useScrollSpy';

let observerCallback = null;
const observed = [];

class FakeObserver {
    constructor(callback) {
        observerCallback = callback;
    }

    observe(element) {
        observed.push(element.id);
    }

    disconnect() {
        observed.length = 0;
    }
}

const Host = defineComponent({
    props: { ids: { type: Array, required: true } },
    setup(props) {
        const current = useScrollSpy(() => props.ids);

        return () => h('div', [h('p', { 'data-current': '' }, current.value), ...props.ids.map((id) => h('section', { id }))]);
    },
});

afterEach(() => {
    vi.unstubAllGlobals();
    observed.length = 0;
});

describe('useScrollSpy', () => {
    it('starts on the first section', () => {
        vi.stubGlobal('IntersectionObserver', FakeObserver);

        expect(mount(Host, { props: { ids: ['a', 'b'] }, attachTo: document.body }).find('[data-current]').text()).toBe('a');
    });

    it('follows the section that enters the reading zone', async () => {
        vi.stubGlobal('IntersectionObserver', FakeObserver);
        const wrapper = mount(Host, { props: { ids: ['a', 'b', 'c'] }, attachTo: document.body });

        expect(observed).toEqual(['a', 'b', 'c']);
        observerCallback([{ isIntersecting: true, target: { id: 'c' } }]);
        await nextTick();

        expect(wrapper.find('[data-current]').text()).toBe('c');
    });

    it('stops watching once the page is left', () => {
        vi.stubGlobal('IntersectionObserver', FakeObserver);
        const wrapper = mount(Host, { props: { ids: ['a', 'b'] }, attachTo: document.body });

        wrapper.unmount();

        expect(observed).toEqual([]);
    });

    it('keeps the first section where the browser cannot observe', () => {
        vi.stubGlobal('IntersectionObserver', undefined);

        expect(mount(Host, { props: { ids: ['a', 'b'] }, attachTo: document.body }).find('[data-current]').text()).toBe('a');
    });
});
