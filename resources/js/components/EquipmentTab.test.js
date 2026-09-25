import { describe, it, expect, vi } from 'vitest';

vi.mock('../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import { mountWithPlugins } from '../tests/helpers';
import EquipmentTab from './EquipmentTab.vue';

const makeCharacter = (equipment = [], ilvl = 630) => ({
    realm: 'hyjal',
    name: 'arthas',
    avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
    ilvl,
    equipment,
});

const fullEquipment = [
    { slot: 'HEAD', slot_name: 'Tête', item_id: 100, name: 'Casque épique', item_level: 639, quality: 'EPIC', icon_url: 'https://wow.zamimg.com/images/wow/icons/medium/123.jpg' },
    { slot: 'NECK', slot_name: 'Cou', item_id: 101, name: 'Collier rare', item_level: 626, quality: 'RARE', icon_url: 'https://wow.zamimg.com/images/wow/icons/medium/124.jpg' },
    { slot: 'MAIN_HAND', slot_name: 'Main droite', item_id: 103, name: 'Épée légendaire', item_level: 645, quality: 'LEGENDARY', icon_url: 'https://wow.zamimg.com/images/wow/icons/medium/125.jpg' },
];

describe('EquipmentTab', () => {
    it('renders equipment heading', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('Équipement');
    });

    it('renders average ilvl', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('630');
    });

    it('renders item names', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('Casque épique');
        expect(wrapper.text()).toContain('Collier rare');
        expect(wrapper.text()).toContain('Épée légendaire');
    });

    it('renders item levels', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('639');
        expect(wrapper.text()).toContain('626');
        expect(wrapper.text()).toContain('645');
    });

    it('renders wowhead links', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        const links = wrapper.findAll('a[href*="wowhead.com/fr/item="]');
        expect(links.length).toBeGreaterThanOrEqual(3);
        expect(links[0].attributes('href')).toContain('/item=');
        expect(links[0].attributes('target')).toBe('_blank');
    });

    it('hands each slot its item, in a single slot list per layout', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        const head = wrapper.findAllComponents({ name: 'EquipmentSlot' }).find((slot) => slot.props('label') === 'Tête');

        expect(head.props('item')).toEqual(fullEquipment[0]);
    });

    it('renders avatar image', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        const img = wrapper.find('img[src="https://render.worldofwarcraft.com/avatar.jpg"]');
        expect(img.exists()).toBe(true);
        expect(img.attributes('alt')).toBe('');
    });

    it('titles the sub-tab in a h2 that folds the equipment', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        const toggle = wrapper.find('h2 button');

        expect(toggle.text()).toContain('Équipement');
        expect(toggle.attributes('aria-expanded')).toBe('true');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.findComponent({ name: 'EquipmentSlot' }).exists()).toBe(false);
    });

    it('renders with empty equipment', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter([]) } });
        expect(wrapper.text()).toContain('Équipement');
    });
});
