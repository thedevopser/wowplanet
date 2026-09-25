import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ErrorState from './ErrorState.vue';

describe('ErrorState', () => {
    it('alerts with a readable cause', () => {
        const wrapper = mount(ErrorState, { props: { message: 'Le classement PvP est momentanément indisponible.' } });

        expect(wrapper.attributes('role')).toBe('alert');
        expect(wrapper.text()).toContain('Le classement PvP est momentanément indisponible.');
    });

    it('titles the error by default, and as told otherwise', () => {
        expect(mount(ErrorState, { props: { message: 'x' } }).find('p.font-semibold').text()).toBe('Une erreur est survenue');
        expect(mount(ErrorState, { props: { message: 'x', title: 'Talents indisponibles' } }).find('p.font-semibold').text()).toBe('Talents indisponibles');
    });

    it('offers to retry as soon as someone listens for it', async () => {
        const onRetry = vi.fn();
        const wrapper = mount(ErrorState, { props: { message: 'x', onRetry } });

        await wrapper.find('button').trigger('click');

        expect(wrapper.find('button').text()).toContain('Réessayer');
        expect(onRetry).toHaveBeenCalledOnce();
    });

    it('offers no retry when nothing can be replayed', () => {
        expect(mount(ErrorState, { props: { message: 'x' } }).find('button').exists()).toBe(false);
    });
});
