import { describe, it, expect, vi } from 'vitest';
import { mountWithPlugins } from '../../tests/helpers';
import { useCharacterStore } from '../../stores/character';
import CrossDataBanner from './CrossDataBanner.vue';

const mountBanner = (status, isOwner = true) => mountWithPlugins(CrossDataBanner, {
    props: { isOwner },
    initialState: { character: { crossCharacterStatus: status } },
});

describe('CrossDataBanner', () => {
    it.each(['ready', 'idle'])('stays silent when the data are %s', async (status) => {
        const wrapper = await mountBanner(status);

        expect(wrapper.text()).toBe('');
    });

    it('stays silent on the sheet of another player', async () => {
        const wrapper = await mountBanner('not_available', false);

        expect(wrapper.text()).toBe('');
    });

    it('says the data are missing, what they are for, and offers to compute them', async () => {
        const wrapper = await mountBanner('not_available');
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(wrapper.text()).toContain('Données de vos autres personnages non calculées');
        expect(wrapper.text()).toContain('ce que vos autres personnages ont déjà fait');

        await wrapper.findAll('button').find((button) => button.text().includes('Lancer le calcul')).trigger('click');

        expect(store.computeCrossCharacter).toHaveBeenCalled();
    });

    it('announces a computation in progress', async () => {
        const wrapper = await mountBanner('loading');

        expect(wrapper.find('[role="status"]').text()).toContain('Calcul des données de vos autres personnages…');
    });

    it('offers to retry a failed computation', async () => {
        const wrapper = await mountBanner('error');
        const store = useCharacterStore(wrapper.vm.$pinia);

        await wrapper.findAll('button').find((button) => button.text().includes('Réessayer')).trigger('click');

        expect(store.computeCrossCharacter).toHaveBeenCalled();
    });
});
