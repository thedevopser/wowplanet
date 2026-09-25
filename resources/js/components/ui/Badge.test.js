import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { classColor, factionColor, qualityColor, rankColor, standingColor } from '../../utils/wowColors';

const theme = vi.hoisted(() => ({ effective: null }));

vi.mock('../../composables/useTheme', async () => {
    const { ref } = await import('vue');
    theme.effective = ref('dark');

    return { useTheme: () => ({ effective: theme.effective }) };
});

import Badge from './Badge.vue';

const mountBadge = (props = {}, slot = 'Épique') => mount(Badge, { props, slots: slot === null ? {} : { default: slot } });

beforeEach(() => {
    theme.effective.value = 'dark';
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Badge', () => {
    it('is neutral by default', () => {
        const wrapper = mountBadge();

        expect(wrapper.text()).toBe('Épique');
        expect(wrapper.classes()).toEqual(expect.arrayContaining(['text-muted', 'border-default', 'rounded-ui-sm']));
    });

    it.each(['info', 'success', 'warning', 'danger'])('takes the %s tone from the semantic tokens', (tone) => {
        expect(mountBadge({ tone }).classes()).toContain(`text-${tone}`);
    });

    it.each([
        ['class', 6, () => classColor(6)],
        ['quality', 'EPIC', () => qualityColor('EPIC')],
        ['faction', 'Horde', () => factionColor('HORDE')],
        ['rank', 'Légendaire', () => rankColor('Légendaire')],
        ['standing', 'renown', () => standingColor('renown')],
    ])('colours a %s badge from the game colours, readable in the current theme', async (tone, value, color) => {
        const wrapper = mountBadge({ tone, value });
        const style = () => wrapper.attributes('style');

        expect(style()).toContain(`color: ${color().onDark}`);
        expect(style()).toContain(`border-color: ${color().base}`);

        theme.effective.value = 'light';
        await wrapper.vm.$nextTick();

        expect(style()).toContain(`color: ${color().onLight}`);
    });

    it('refuses a game tone without the value it colours', () => {
        expect(() => mountBadge({ tone: 'class' })).toThrow('class');
    });

    it('warns when it carries no text, since a colour alone says nothing', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mountBadge({ tone: 'success' }, null);

        expect(warn).toHaveBeenCalledWith(expect.stringContaining('[Badge]'));
    });

    it('warns about a tone outside the list', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mountBadge({ tone: 'gold' });

        expect(warn.mock.calls.map(([message]) => message).join('\n')).toContain('prop "tone"');
    });
});

