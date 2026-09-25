import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { mount } from '@vue/test-utils';
import DiscordInvite from './DiscordInvite.vue';

describe('DiscordInvite', () => {
    it('opens the Discord server in a new tab, without leaking the opener', () => {
        const link = mount(DiscordInvite).find('a');

        expect(link.attributes('href')).toBe('https://discord.gg/wa49gGF8cr');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
    });

    it('tells a screen reader that the link leaves the site', () => {
        expect(mount(DiscordInvite).find('a').text()).toContain('nouvel onglet');
    });

    it('fills its button with the brand token, never a raw colour', () => {
        const source = readFileSync(resolve(__dirname, 'DiscordInvite.vue'), 'utf8');

        expect(mount(DiscordInvite).find('a').classes()).toEqual(expect.arrayContaining(['bg-brand-discord', 'text-on-brand-discord']));
        expect(source).not.toMatch(/\btext-white\b/);
        expect(source).not.toMatch(/#[0-9a-f]{6}/i);
    });
});
