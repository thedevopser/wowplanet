import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('../../composables/useTheme', async () => {
    const { ref } = await import('vue');

    return { useTheme: () => ({ effective: ref('dark') }) };
});

import { qualityColor } from '../../utils/wowColors';
import EquipmentSlot from './EquipmentSlot.vue';

const ITEM = { slot: 'HEAD', item_id: 100, name: 'Casque épique', item_level: 639, quality: 'EPIC', icon_url: 'https://render.example/123.jpg' };

describe('EquipmentSlot', () => {
    it('links an equipped item to Wowhead, named, with its item level', () => {
        const wrapper = mount(EquipmentSlot, { props: { label: 'Tête', item: ITEM } });
        const link = wrapper.find('a');

        expect(link.attributes('href')).toBe('https://www.wowhead.com/fr/item=100');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.text()).toContain('Casque épique');
        expect(link.text()).toContain('Tête');
        expect(link.text()).toContain('639');
    });

    it('writes the item in the readable colour of its quality, framed by the official one', () => {
        const wrapper = mount(EquipmentSlot, { props: { label: 'Tête', item: ITEM } });

        expect(wrapper.find('[data-item-name]').attributes('style')).toContain(qualityColor('EPIC').onDark);
        expect(wrapper.find('[data-item-icon]').attributes('style')).toContain(qualityColor('EPIC').base);
    });

    it('shows an item of unknown quality uncoloured', () => {
        const wrapper = mount(EquipmentSlot, { props: { label: 'Tête', item: { ...ITEM, quality: 'MYTHIC' } } });

        expect(wrapper.find('[data-item-name]').attributes('style')).toBeUndefined();
    });

    it('marks an empty slot as such, without a link', () => {
        const wrapper = mount(EquipmentSlot, { props: { label: 'Chemise', item: null } });

        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.text()).toContain('Chemise');
        expect(wrapper.text()).toContain('Vide');
    });
});
