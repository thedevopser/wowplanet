import { describe, it, expect, vi, afterEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('../utils/scoreCardRenderer', () => ({
    renderScoreCard: vi.fn(() => ({ width: 700, height: 430 })),
}));

import { renderScoreCard } from '../utils/scoreCardRenderer';
import ShareScoreModal from './ShareScoreModal.vue';
import { mountWithPlugins } from '../tests/helpers';

let wrapper;

async function mountModal(props = {}) {
    wrapper = mount(ShareScoreModal, {
        props: { show: true, variant: 'personal', scoreData: { globalScore: 75, rank: 'Épique' }, ...props },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const dialog = () => document.querySelector('[role="dialog"]');
const button = (label) => [...dialog().querySelectorAll('button')].find((entry) => entry.textContent.includes(label) || entry.getAttribute('aria-label') === label);

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('ShareScoreModal', () => {
    it('renders nothing while closed', async () => {
        await mountModal({ show: false });

        expect(dialog()).toBeNull();
    });

    it('is a dialog named « Partager ce score »', async () => {
        await mountModal();

        expect(document.getElementById(dialog().getAttribute('aria-labelledby')).textContent).toBe('Partager ce score');
    });

    it('names the account variant after the account', async () => {
        await mountModal({ variant: 'account' });

        expect(document.getElementById(dialog().getAttribute('aria-labelledby')).textContent).toBe('Partager le score du compte');
    });

    it('opens wide enough to show the card close to its real size', async () => {
        await mountModal();

        expect(dialog().className).toContain('max-w-3xl');
    });

    it('draws the share card from the score data once open', async () => {
        await mountModal();

        expect(renderScoreCard).toHaveBeenCalledWith({ globalScore: 75, rank: 'Épique' }, expect.objectContaining({ title: 'Score de complétion' }));
        expect(dialog().querySelector('canvas')).not.toBeNull();
    });

    it('describes the card for assistive technologies', async () => {
        await mountModal();

        expect(dialog().querySelector('canvas').getAttribute('aria-label')).toBe('Carte de score à partager : 75 sur 100, rang Épique');
    });

    it('reports its closing, from its named close button or from Escape', async () => {
        await mountModal();

        button('Fermer').click();
        await flushPromises();

        expect(wrapper.emitted('close')).toHaveLength(1);

        await wrapper.setProps({ show: false });
        await wrapper.setProps({ show: true });
        await flushPromises();
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(wrapper.emitted('close')).toHaveLength(2);
    });

    it('offers to download and to copy the image', async () => {
        await mountModal();

        expect(button('Télécharger')).toBeDefined();
        expect(button('Copier l’image')).toBeDefined();
    });
});

describe('ShareScoreModal in english', () => {
    it('speaks english and draws the card in english', async () => {
        wrapper = await mountWithPlugins(ShareScoreModal, {
            locale: 'en',
            props: { show: true, variant: 'personal', scoreData: { globalScore: 75.5, rank: 'Épique' } },
            attachTo: document.body,
        });
        await flushPromises();

        expect(dialog().textContent).toContain('Share this score');
        expect(button('Download')).toBeDefined();
        expect(document.querySelector('canvas').getAttribute('aria-label')).toBe('Score card to share: 75.5 out of 100, rank Epic');
        const labels = renderScoreCard.mock.calls.at(-1)[1];
        expect(labels.title).toBe('Completion score');
        expect(labels.rank('Épique')).toBe('Epic');
        expect(labels.charactersAnalysed(3)).toBe('3 characters analysed');
    });
});
