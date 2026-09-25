import { describe, it, expect } from 'vitest';
import { mountWithPlugins, LEGACY_PALETTE } from '../../tests/helpers';
import AdminStatusBadge from './AdminStatusBadge.vue';

const mountBadge = (props) => mountWithPlugins(AdminStatusBadge, { props });

describe('AdminStatusBadge', () => {
    it('names a health status in words, in the tone of its gravity', async () => {
        const wrapper = await mountBadge({ kind: 'health', status: 'unavailable' });

        expect(wrapper.text()).toBe('Injoignable');
        expect(wrapper.attributes('data-status')).toBe('unavailable');
        expect(wrapper.classes()).toContain('text-danger');
    });

    it('names an import status in words', async () => {
        const wrapper = await mountBadge({ kind: 'import', status: 'cancelled' });

        expect(wrapper.text()).toBe('Annulé');
        expect(wrapper.classes()).toContain('text-warning');
    });

    it('keeps its label on one line', async () => {
        expect((await mountBadge({ kind: 'import', status: 'failed' })).classes()).toContain('whitespace-nowrap');
    });

    it('draws only with the tokens of the design system', async () => {
        expect((await mountBadge({ kind: 'health', status: 'ok' })).html()).not.toMatch(LEGACY_PALETTE);
    });
});
