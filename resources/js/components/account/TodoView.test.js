import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';

const inertia = vi.hoisted(() => ({ page: null, router: { replace: vi.fn() } }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    inertia.page = reactive({ url: '/mon-compte/a-faire', props: {} });
    inertia.router.replace.mockImplementation(({ url }) => {
        inertia.page.url = url;
    });

    return { usePage: () => inertia.page, router: inertia.router };
});

vi.mock('axios');

import { mountWithPlugins } from '../../tests/helpers';
import Select from '../ui/Select.vue';
import TodoView from './TodoView.vue';

const account = { scope: 'account', characters: [] };
const by = (...characters) => ({ scope: 'characters', characters });
const item = (id, name, doers, detail = null) => ({ id, name, wowheadUrl: `https://www.wowhead.com/fr/quest=${id}`, detail, doers });

const category = (key, name, dimension, expansionId, missingItems, { completed = 1, missingMore = 0 } = {}) => ({
    key,
    name,
    dimension,
    expansionId,
    completed,
    total: completed + missingItems.length + missingMore,
    missing: missingItems.length + missingMore,
    percent: 50,
    missingItems,
    missingMore,
});

const mounts = category('mounts:Butin', 'Butin', 'mounts', null, [item(1, 'Proto-drake', account), item(2, 'Raptor', account)], { completed: 18 });
const comte = category('quests:10:Comté-du-Nord', 'Comté-du-Nord', 'quests', 10,
    [1, 2, 3, 4, 5, 6, 7].map((id) => item(100 + id, `Quête ${id}`, id === 1 ? by('Apery') : by('Apery', 'Jaina'))), { missingMore: 15 });
const plaines = category('quests:9:Plaines', 'Plaines d’Ohn’ahra', 'quests', 9, [item(200, 'Les Centaures', by('Jaina'))]);
const reputations = category('reputations:10', 'Réputations', 'reputations', 10, [item(2594, "L'Assemblée des abysses", by('Jaina'), 'Renom 12')]);
const achievements = [1, 2, 3, 4, 5].map((index) => category(`achievements:10:${index}`, `Hauts-faits ${index}`, 'achievements', 10, [item(300 + index, `Haut-fait ${index}`, account)]));

const todo = {
    categories: [mounts, comte, reputations, ...achievements, plaines],
    characters: ['Apery', 'Jaina'],
    updatedAt: '2026-11-14T09:00:00Z',
};

const readyWith = (data) => ({ data: { status: 'ready', data: data } });
const ready = readyWith(todo);

const mountView = () => mountWithPlugins(TodoView);

const cardNames = (wrapper) => wrapper.findAll('[data-todo-category] [data-category-name]').map((entry) => entry.text());
const card = (wrapper, name) => wrapper.findAll('[data-todo-category]').find((entry) => entry.find('[data-category-name]').text() === name);
const lastUrl = () => inertia.router.replace.mock.calls.at(-1)[0].url;

const goTo = (url) => {
    inertia.page.url = url;
};

describe('TodoView', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.clearAllMocks();
        goTo('/mon-compte/a-faire');
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('overview', () => {
        it('opens on the six categories closest to the end, in the order received', async () => {
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            expect(axios.get).toHaveBeenCalledWith('/api/account/todo');
            expect(wrapper.find('[data-nearly-done] h2').text()).toBe('Presque fini');
            expect(cardNames(wrapper)).toEqual(['Butin', 'Comté-du-Nord', 'Réputations', 'Hauts-faits 1', 'Hauts-faits 2', 'Hauts-faits 3']);
        });

        it('shows on each card how far it is, what it misses and who can do it', async () => {
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            const quests = card(wrapper, 'Comté-du-Nord');
            expect(quests.text()).toContain('Quêtes · The War Within');
            expect(quests.text()).toContain('1 / 23');
            expect(quests.find('[data-missing]').text()).toBe('22');

            const items = quests.findAll('[data-todo-item]');
            expect(items).toHaveLength(5);
            expect(items[0].find('a').attributes('href')).toBe('https://www.wowhead.com/fr/quest=101');
            expect(items[0].find('[data-doers]').text()).toBe('Apery');
            expect(items[1].find('[data-doers]').text()).toBe('Tous vos personnages');

            expect(card(wrapper, 'Butin').find('[data-doers]').text()).toBe('Tout le compte');
            expect(card(wrapper, 'Réputations').find('[data-todo-item]').text()).toContain('Renom 12');
        });

        it('unfolds the rest of a card on demand', async () => {
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            const quests = card(wrapper, 'Comté-du-Nord');
            const more = quests.find('[data-show-more]');
            expect(more.text()).toBe('Voir les 2 autres');
            expect(more.attributes('aria-expanded')).toBe('false');

            await more.trigger('click');

            expect(quests.findAll('[data-todo-item]')).toHaveLength(7);
            expect(quests.text()).toContain('… et 15 autres');
            expect(quests.find('[data-show-more]').text()).toBe('Voir moins');
        });

        it('sums up each dimension and opens it on demand', async () => {
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            const tiles = wrapper.findAll('[data-dimension-tile]');
            expect(tiles.map((tile) => tile.find('[data-dimension-label]').text())).toEqual(['Montures', 'Quêtes', 'Réputations', 'Hauts-faits']);
            expect(tiles[1].text()).toContain('2 catégories');
            expect(tiles[1].text()).toContain('23 manquants');

            await tiles[1].trigger('click');

            expect(lastUrl()).toBe('/mon-compte/a-faire?dimension=quests');
        });
    });

    describe('a dimension', () => {
        it('lists every category of the dimension of the address', async () => {
            goTo('/mon-compte/a-faire?dimension=quests');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            expect(wrapper.find('h2').text()).toBe('Quêtes');
            expect(cardNames(wrapper)).toEqual(['Comté-du-Nord', 'Plaines d’Ohn’ahra']);
            expect(wrapper.find('[data-dimension-tile]').exists()).toBe(false);
        });

        it('narrows the dimension to an expansion, latest first', async () => {
            goTo('/mon-compte/a-faire?dimension=quests');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            const chips = wrapper.findAll('[data-expansion]');
            expect(chips.map((chip) => chip.text())).toEqual(['Toutes 2', 'The War Within 1', 'Dragonflight 1']);
            expect(chips[0].attributes('aria-pressed')).toBe('true');

            await chips[2].trigger('click');
            await flushPromises();

            expect(lastUrl()).toBe('/mon-compte/a-faire?dimension=quests&extension=9');
            expect(cardNames(wrapper)).toEqual(['Plaines d’Ohn’ahra']);
        });

        it('pages a long dimension by twelve', async () => {
            goTo('/mon-compte/a-faire?dimension=achievements');
            const many = Array.from({ length: 13 }, (_, index) => category(`achievements:10:${index}`, `Catégorie ${index}`, 'achievements', 10, [item(index, `Haut-fait ${index}`, account)]));
            axios.get.mockResolvedValueOnce(readyWith({ ...todo, categories: many }));
            window.scrollTo = vi.fn();

            const wrapper = await mountView();
            await flushPromises();

            expect(cardNames(wrapper)).toHaveLength(12);

            await wrapper.find('[aria-label="Page 2"]').trigger('click');

            expect(cardNames(wrapper)).toEqual(['Catégorie 12']);
        });

        it('comes back to the overview, forgetting the expansion', async () => {
            goTo('/mon-compte/a-faire?dimension=quests&extension=9&personnage=Jaina');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            await wrapper.find('[data-back]').trigger('click');

            expect(inertia.router.replace).toHaveBeenCalledOnce();
            expect(lastUrl()).toBe('/mon-compte/a-faire?personnage=Jaina');
        });

        it('ignores a dimension or an expansion of the address the list does not know', async () => {
            goTo('/mon-compte/a-faire?dimension=raids&extension=3');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            expect(wrapper.find('[data-nearly-done]').exists()).toBe(true);
        });
    });

    describe('the character filter', () => {
        it('keeps what the character of the address can do, the whole account included', async () => {
            goTo('/mon-compte/a-faire?dimension=quests&personnage=Apery');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            expect(cardNames(wrapper)).toEqual(['Comté-du-Nord']);
        });

        it('writes the chosen character into the address, and ignores one the account does not have', async () => {
            goTo('/mon-compte/a-faire?personnage=Thrall');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            const select = wrapper.findComponent(Select);
            expect(select.props('modelValue')).toBe('tout');
            expect(select.props('options').map((option) => option.value)).toEqual(['tout', 'Apery', 'Jaina']);

            select.vm.$emit('update:modelValue', 'Jaina');
            await flushPromises();

            expect(lastUrl()).toBe('/mon-compte/a-faire?personnage=Jaina');
        });

        it('says when the filters leave nothing', async () => {
            goTo('/mon-compte/a-faire?dimension=reputations&personnage=Apery');
            axios.get.mockResolvedValueOnce(ready);

            const wrapper = await mountView();
            await flushPromises();

            expect(wrapper.text()).toContain('Rien pour ce personnage');
        });
    });

    it('says when nothing is left to do on the account', async () => {
        axios.get.mockResolvedValueOnce(readyWith({ categories: [], characters: [], updatedAt: null }));

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.text()).toContain('Rien à faire');
        expect(wrapper.findAllComponents(Select)).toHaveLength(0);
    });

    it('dates the list', async () => {
        axios.get.mockResolvedValueOnce(ready);

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-updated-at]').text()).toMatch(/^Mis à jour le 14 nov\./);
    });

    it('shows how far the account score is while it loads the characters, then asks again', async () => {
        axios.get
            .mockResolvedValueOnce({ data: { status: 'computing', step: 'account', progress: { loaded: 1, errors: 0, total: 3, current: 'Jaina' } } })
            .mockResolvedValueOnce(ready);

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-computing] [role="status"]').text()).toContain('Analyse de Jaina…');
        expect(wrapper.find('[role="progressbar"]').attributes('aria-label')).toBe('Personnages analysés : 1 sur 3');

        await vi.advanceTimersByTimeAsync(2500);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-nearly-done]').exists()).toBe(true);
    });

    it('says it compares the characters while their computation runs', async () => {
        axios.get.mockResolvedValueOnce({ data: { status: 'computing', step: 'characters', jobId: 'job-1' } });

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[data-computing] [role="status"]').text()).toContain('Comparaison de vos personnages');
    });

    it('reports a failed computation and tries again on demand', async () => {
        axios.get.mockRejectedValueOnce({ response: { status: 502, data: { status: 'failed' } } });

        const wrapper = await mountView();
        await flushPromises();

        const alert = wrapper.find('[role="alert"]');
        expect(alert.text()).toContain('Le calcul de vos personnages a échoué');

        axios.get.mockReturnValueOnce(new Promise(() => {}));
        await alert.find('button').trigger('click');

        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('waits and asks again when it is asking too often, without reporting an error', async () => {
        axios.get
            .mockResolvedValueOnce({ data: { status: 'computing', step: 'characters', jobId: 'job-1' } })
            .mockRejectedValueOnce({ response: { status: 429, data: {} } })
            .mockResolvedValueOnce(ready);

        const wrapper = await mountView();
        await flushPromises();
        await vi.advanceTimersByTimeAsync(2500);
        await flushPromises();

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('[data-computing]').exists()).toBe(true);

        await vi.advanceTimersByTimeAsync(9999);
        expect(axios.get).toHaveBeenCalledTimes(2);

        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(3);
        expect(wrapper.find('[data-nearly-done]').exists()).toBe(true);
    });

    it('reports any other error', async () => {
        axios.get.mockRejectedValueOnce({ response: { status: 500, data: {} } });

        const wrapper = await mountView();
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('La liste n’a pas pu être chargée');
    });

    it('updates the list on demand', async () => {
        axios.get.mockResolvedValueOnce(ready);

        const wrapper = await mountView();
        await flushPromises();

        axios.post.mockResolvedValueOnce({});
        axios.get.mockReturnValueOnce(new Promise(() => {}));

        const button = wrapper.findAll('button').find((entry) => entry.text().includes('Mettre à jour'));
        await button.trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/account/todo/refresh');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('stops asking once it leaves the page', async () => {
        axios.get.mockResolvedValueOnce({ data: { status: 'computing', step: 'characters', jobId: 'job-1' } });

        const wrapper = await mountView();
        await flushPromises();
        wrapper.unmount();

        await vi.advanceTimersByTimeAsync(5000);

        expect(axios.get).toHaveBeenCalledTimes(1);
    });
});

describe('TodoView in english', () => {
    it('speaks english', async () => {
        axios.get.mockResolvedValueOnce(ready);

        const wrapper = await mountWithPlugins(TodoView, { locale: 'en' });
        await flushPromises();

        expect(wrapper.text()).toContain('Nearly done');
        expect(wrapper.text()).toContain('By dimension');
        expect(wrapper.find('[data-dimension-label]').text()).toBe('Mounts');
        expect(wrapper.findAllComponents({ name: 'Select' })[0].props('label')).toBe('Character');
        expect(wrapper.text()).not.toMatch(/Presque fini|Par dimension|manquant|Tout le compte/);
    });
});
