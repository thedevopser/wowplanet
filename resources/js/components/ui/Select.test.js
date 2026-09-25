import { describe, it, expect, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Select from './Select.vue';

const OPTIONS = [
    { value: '11', label: 'Midnight', hint: '294 / 1 005', progress: { value: 294, max: 1005, color: '#3C3CDD' } },
    { value: '10', label: 'The War Within', hint: '147 / 1 808', progress: { value: 147, max: 1808 } },
    { value: '0', label: 'Classic' },
];

let wrapper;

async function mountSelect(props = {}) {
    wrapper = mount(Select, {
        props: { label: 'Extension', options: OPTIONS, modelValue: '11', ...props },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const trigger = () => document.querySelector('[role="combobox"]');
const listbox = () => document.querySelector('[role="listbox"]');
const options = () => [...document.querySelectorAll('[role="option"]')];

async function open() {
    trigger().focus();
    trigger().dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    await flushPromises();
}

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('Select', () => {
    it('is a combobox named by its visible label', async () => {
        await mountSelect();

        const label = document.getElementById(trigger().getAttribute('aria-labelledby'));

        expect(label.textContent.trim()).toBe('Extension');
    });

    it('shows the chosen option in its trigger', async () => {
        await mountSelect({ modelValue: '10' });

        expect(trigger().textContent).toContain('The War Within');
    });

    it('stays closed until opened, from the keyboard too', async () => {
        await mountSelect();

        expect(listbox()).toBeNull();

        await open();

        expect(options().map((option) => option.querySelector('[data-option-label]').textContent.trim())).toEqual(['Midnight', 'The War Within', 'Classic']);
    });

    it('shows the hint and the progress of each option', async () => {
        await mountSelect();
        await open();

        expect(options()[0].textContent).toContain('294 / 1 005');
        expect(options()[0].querySelector('[data-option-progress]').getAttribute('style')).toContain('width: 29.25');
        expect(options()[0].querySelector('[data-option-progress]').getAttribute('style')).toContain('#3C3CDD');
        expect(options()[1].querySelector('[data-option-progress]').className).toContain('bg-accent');
        expect(options()[2].querySelector('[data-option-progress]')).toBeNull();
    });

    it('marks the chosen option', async () => {
        await mountSelect({ modelValue: '10' });
        await open();

        expect(options().map((option) => option.getAttribute('aria-selected'))).toEqual(['false', 'true', 'false']);
    });

    it('reports the option picked', async () => {
        await mountSelect();
        await open();

        options()[2].dispatchEvent(new PointerEvent('pointerup', { bubbles: true }));
        options()[2].click();
        await flushPromises();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['0']);
    });
});
