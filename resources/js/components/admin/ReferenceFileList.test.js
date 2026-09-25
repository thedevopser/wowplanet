import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import ReferenceFileList from './ReferenceFileList.vue';

const file = (overrides = {}) => ({
    filename: 'faction-12.1.0.69875.csv',
    state: 'live',
    bytes: 157_683,
    source_table: 'Faction',
    build: '12.1.0.69875',
    loaded_at: 1_758_358_502,
    ...overrides,
});

const obsolete = () => file({ filename: 'faction-12.1.0.69587.csv', state: 'obsolete', build: '12.1.0.69587' });
const orphan = () => file({ filename: 'spell_name.csv', state: 'orphan', bytes: 4_096, source_table: null, build: null, loaded_at: null });
const taxonomy = () => file({ filename: 'mounts.json', state: 'taxonomy', bytes: 390_055, source_table: null, build: null, loaded_at: null });

const mountList = (files = [file()], props = {}) => mountWithPlugins(ReferenceFileList, {
    props: { files, missing: [], selected: [], disabled: false, ...props },
});

beforeEach(() => vi.clearAllMocks());

describe('ReferenceFileList', () => {
    it('names each file with what it weighs', async () => {
        const wrapper = await mountList();

        expect(wrapper.text()).toContain('faction-12.1.0.69875.csv');
        expect(wrapper.text()).toContain('154 ko');
    });

    it('says which file is the one in service', async () => {
        const wrapper = await mountList();

        expect(wrapper.get('[data-state="live"]').text()).toContain('En service');
    });

    it('marks a file left behind by a newer load as obsolete', async () => {
        const wrapper = await mountList([obsolete()]);

        expect(wrapper.get('[data-state="obsolete"]').text()).toContain('Obsolète');
    });

    it('marks a file the inventory never heard of as an orphan', async () => {
        const wrapper = await mountList([orphan()]);

        expect(wrapper.get('[data-state="orphan"]').text()).toContain('Orphelin');
    });

    it('sets an upstream taxonomy snapshot apart, so it never reads as an orphan', async () => {
        const wrapper = await mountList([taxonomy()]);

        expect(wrapper.get('[data-state="taxonomy"]').text()).toContain('Taxonomie');
        expect(wrapper.text()).not.toContain('Orphelin');
    });

    it('offers no selection on the file in service, which a sweep must not take', async () => {
        const wrapper = await mountList([file(), obsolete()]);

        expect(wrapper.findAll('[data-action="select-file"]')).toHaveLength(1);
    });

    it('offers no selection on a taxonomy snapshot either', async () => {
        const wrapper = await mountList([taxonomy()]);

        expect(wrapper.find('[data-action="select-file"]').exists()).toBe(false);
    });

    it('asks for a file to be selected, by its name', async () => {
        const wrapper = await mountList([obsolete()]);

        await wrapper.get('[data-action="select-file"]').setValue(true);

        expect(wrapper.emitted('update:selected')).toEqual([[['faction-12.1.0.69587.csv']]]);
    });

    it('drops a file from the selection when it is unticked', async () => {
        const wrapper = await mountList([obsolete()], { selected: ['faction-12.1.0.69587.csv'] });

        await wrapper.get('[data-action="select-file"]').setValue(false);

        expect(wrapper.emitted('update:selected')).toEqual([[[]]]);
    });

    it('asks for a single file to be removed, whatever its state', async () => {
        const wrapper = await mountList();

        await wrapper.get('[data-action="delete-file"]').trigger('click');

        expect(wrapper.emitted('delete')).toEqual([['faction-12.1.0.69875.csv']]);
    });

    it('offers nothing while something is already running', async () => {
        const wrapper = await mountList([obsolete()], { disabled: true });

        expect(wrapper.get('[data-action="delete-file"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-action="select-file"]').attributes('disabled')).toBeDefined();
    });

    it('reports an inventory row whose file has gone, rather than staying silent', async () => {
        const wrapper = await mountList([], {
            missing: [{ filename: 'faction-12.1.0.69587.csv', source_table: 'Faction', build: '12.1.0.69587', loaded_at: 1_757_693_866 }],
        });

        expect(wrapper.get('[data-alert="missing"]').text()).toContain('faction-12.1.0.69587.csv');
    });

    it('stays quiet about missing files when there are none', async () => {
        const wrapper = await mountList();

        expect(wrapper.find('[data-alert="missing"]').exists()).toBe(false);
    });

    it('says the store is empty rather than showing a bare table', async () => {
        const wrapper = await mountList([]);

        expect(wrapper.text()).toContain('vide');
    });
});
