import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

import axios from 'axios';
import { LEGACY_PALETTE, mountWithPlugins } from '../../tests/helpers';
import DiscordComposer from './DiscordComposer.vue';

vi.mock('axios');

const buttonLabelled = (wrapper, label) => wrapper.findAll('button').find(b => b.text() === label);

const fillAnnouncement = async wrapper => {
    await wrapper.findAll('input')[0].setValue('Nouvelle version');
    await wrapper.find('textarea').setValue('**Score** revu');
};

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
});

afterEach(() => vi.useRealTimers());

describe('DiscordComposer', () => {
    it('requires a title and a description before sending', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);

        expect(buttonLabelled(wrapper, 'Envoyer').attributes('disabled')).toBeDefined();

        await fillAnnouncement(wrapper);

        expect(buttonLabelled(wrapper, 'Envoyer').attributes('disabled')).toBeUndefined();
    });

    it('previews the description as rendered markdown', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);

        expect(wrapper.find('.discord-markdown').html()).toContain('<strong>Score</strong>');
    });

    it('sends the announcement and clears the form', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);
        await buttonLabelled(wrapper, 'Envoyer').trigger('click');
        await wrapper.vm.$nextTick();

        expect(axios.post).toHaveBeenCalledWith('/api/admin/discord', {
            channel: 'changelog',
            title: 'Nouvelle version',
            description: '**Score** revu',
            color: 3447003,
        });
        expect(wrapper.text()).toContain('Envoyé avec succès');
        expect(wrapper.findAll('input')[0].element.value).toBe('');
    });

    it('keeps the form filled when the announcement fails', async () => {
        axios.post = vi.fn().mockRejectedValue(new Error('boom'));

        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);
        await buttonLabelled(wrapper, 'Envoyer').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Échec de l’envoi");
        expect(wrapper.findAll('input')[0].element.value).toBe('Nouvelle version');
    });

    it('clears the result message after a few seconds', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);
        await buttonLabelled(wrapper, 'Envoyer').trigger('click');
        await vi.advanceTimersByTimeAsync(5000);

        expect(wrapper.text()).not.toContain('Envoyé avec succès');
    });

    it('sends the chosen channel and color', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);
        await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'discussion');
        await wrapper.find('button[aria-label="Vert"]').trigger('click');
        await buttonLabelled(wrapper, 'Envoyer').trigger('click');
        await wrapper.vm.$nextTick();

        expect(axios.post.mock.calls[0][1]).toMatchObject({ channel: 'discussion', color: 3066993 });
    });

    it('sends only the fields that are filled in', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);
        await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');
        await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');

        const inputs = wrapper.findAll('input');
        await inputs[1].setValue('Score');
        await inputs[2].setValue('Recalculé');

        await buttonLabelled(wrapper, 'Envoyer').trigger('click');
        await wrapper.vm.$nextTick();

        expect(axios.post.mock.calls[0][1].fields).toEqual([{ name: 'Score', value: 'Recalculé', inline: false }]);
    });

    it('removes a field from the announcement', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');

        expect(wrapper.find('button[aria-label="Retirer le champ 1"]').exists()).toBe(true);

        await wrapper.find('button[aria-label="Retirer le champ 1"]').trigger('click');

        expect(wrapper.find('button[aria-label="Retirer le champ 1"]').exists()).toBe(false);
    });

    it('sends the footer when one is given', async () => {
        axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);
        await wrapper.findAll('input').at(-1).setValue('WowPlanet');
        await buttonLabelled(wrapper, 'Envoyer').trigger('click');
        await wrapper.vm.$nextTick();

        expect(axios.post.mock.calls[0][1].footer).toBe('WowPlanet');
    });

    it('chooses its channel in the select of the design system', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        const channel = wrapper.findComponent({ name: 'Select' });

        expect(channel.props('label')).toBe('Canal');
        expect(channel.props('options').map((option) => option.label)).toEqual(['Changelog', 'Discussion']);
    });

    it('names each colour and says which one is chosen', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        await wrapper.find('button[aria-label="Vert"]').trigger('click');

        expect(wrapper.find('button[aria-label="Vert"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('button[aria-label="Bleu"]').attributes('aria-pressed')).toBe('false');
    });

    it('ties every field to its visible label', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);

        ['Titre', 'Description', 'Footer'].forEach((label) => {
            const target = wrapper.findAll('label').find((entry) => entry.text() === label).attributes('for');
            expect(wrapper.find(`#${target}`).exists()).toBe(true);
        });
    });

    it('names the inputs of an added field', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');

        expect(wrapper.find('input[aria-label="Nom du champ 1"]').exists()).toBe(true);
        expect(wrapper.find('input[aria-label="Valeur du champ 1"]').exists()).toBe(true);
    });

    // The preview shows the message as Discord draws it, whatever the theme of the panel.
    it('previews the embed in the colours of Discord, declared as brand tokens', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);

        expect(wrapper.get('[data-discord-preview]').classes()).toEqual(expect.arrayContaining(['bg-brand-discord-embed', 'text-brand-discord-text']));
    });

    it('draws only with the tokens of the design system', async () => {
        const wrapper = await mountWithPlugins(DiscordComposer);
        await fillAnnouncement(wrapper);

        expect(wrapper.html()).not.toMatch(LEGACY_PALETTE);
    });
});
