import { describe, it, expect, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Combobox from './Combobox.vue';
import { LEGACY_PALETTE } from '../../tests/helpers';

const OPTIONS = ['Classic', 'Cataclysm', 'Legion', 'Mounts'];

let wrapper;

async function mountCombobox(props = {}) {
    wrapper = mount(Combobox, {
        props: { label: 'Catégorie', options: OPTIONS, modelValue: '', 'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }), ...props },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const input = () => wrapper.get('input');
const listed = () => [...document.querySelectorAll('[role="option"]')].map((option) => option.textContent.trim());

async function type(text) {
    await input().setValue(text);
    await flushPromises();
}

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('Combobox', () => {
    it('names its field by its visible label', async () => {
        await mountCombobox();

        expect(document.querySelector(`label[for="${input().attributes('id')}"]`).textContent.trim()).toBe('Catégorie');
        expect(input().attributes('role')).toBe('combobox');
    });

    it('accepts a value that is not among the suggestions', async () => {
        await mountCombobox();
        await type('Nouvelle catégorie');

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Nouvelle catégorie']);
    });

    it('suggests only the known values that contain what is typed', async () => {
        await mountCombobox();
        await type('cl');

        expect(listed()).toEqual(['Classic', 'Cataclysm']);
    });

    it('offers every known value from its button, before anything is typed', async () => {
        await mountCombobox();
        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(listed()).toEqual(OPTIONS);
        expect(wrapper.get('button').attributes('aria-label')).toBe('Afficher les valeurs de Catégorie');
    });

    it('takes a suggestion as its value once it is chosen', async () => {
        await mountCombobox();
        await type('leg');
        document.querySelector('[role="option"]').click();
        await flushPromises();

        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Legion']);
        expect(input().element.value).toBe('Legion');
    });

    it('says a value matching no suggestion will be a new one', async () => {
        await mountCombobox();
        await type('Brand new');

        expect(listed()).toEqual([]);
        expect(document.querySelector('[data-combobox-new]').textContent).toContain('« Brand new »');
    });

    it('draws with the tokens of the design system, open list included', async () => {
        await mountCombobox();
        await type('c');

        expect(wrapper.html()).not.toMatch(LEGACY_PALETTE);
        expect(document.body.innerHTML).not.toMatch(LEGACY_PALETTE);
    });
});
