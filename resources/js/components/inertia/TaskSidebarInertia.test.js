import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { nextTick } from 'vue';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return {
        __page: page,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: { visit: vi.fn(), on: vi.fn(() => () => {}) },
    };
});

import { __page } from '@inertiajs/vue3';
import { useCharacterStore } from '../../stores/character';
import { useTaskStore } from '../../stores/tasks';
import { mountWithPlugins } from '../../tests/helpers';
import TaskSidebarInertia from './TaskSidebarInertia.vue';

const makeTask = (overrides = {}) => ({
    id: 1,
    realm_slug: 'hyjal',
    character_name: 'arthas',
    name: 'Coffre hebdomadaire',
    reset_type: 'weekly',
    is_completed: false,
    ...overrides,
});

let wrapper;

async function mountSidebar(options = {}) {
    wrapper = await mountWithPlugins(TaskSidebarInertia, {
        initialState: {
            tasks: { sidebarOpen: true, tasks: [makeTask()], ...(options.tasks || {}) },
            character: { isAuthenticated: true, ...(options.character || {}) },
        },
        stubActions: options.stubActions,
        attachTo: document.body,
    });

    return wrapper;
}

const panel = () => wrapper.find('[data-testid="sidebar-panel"]');
const toggle = () => wrapper.find('[data-testid="sidebar-toggle"]');
const sections = () => wrapper.findAll('[data-testid="character-section"]');
const headers = () => wrapper.findAll('[data-testid="character-header"]');
const expandFirst = async () => headers()[0].trigger('click');
const taskItems = () => wrapper.findAll('[data-testid="task-item"]');

beforeEach(() => {
    __page.url = '/';
    __page.props = {};
});

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('TaskSidebarInertia', () => {
    describe('toggle', () => {
        it('keeps the panel closed until used', async () => {
            await mountSidebar({ tasks: { sidebarOpen: false } });
            const taskStore = useTaskStore(wrapper.vm.$pinia);

            expect(panel().exists()).toBe(false);
            expect(toggle().attributes('aria-expanded')).toBe('false');

            await toggle().trigger('click');

            expect(taskStore.toggleSidebar).toHaveBeenCalled();
        });

        it('controls the panel it opens', async () => {
            await mountSidebar();

            expect(toggle().attributes('aria-expanded')).toBe('true');
            expect(toggle().attributes('aria-controls')).toBe(panel().attributes('id'));
        });

        it('names itself with the number of pending tasks', async () => {
            await mountSidebar({ tasks: { tasks: [makeTask(), makeTask({ id: 2 }), makeTask({ id: 3, is_completed: true })] } });

            expect(toggle().attributes('aria-label')).toBe('Mes tâches, 2 en attente');
            expect(wrapper.find('[data-testid="pending-badge"]').text()).toBe('2');
        });

        it('shows no count when nothing is pending', async () => {
            await mountSidebar({ tasks: { tasks: [makeTask({ is_completed: true })] } });

            expect(toggle().attributes('aria-label')).toBe('Mes tâches');
            expect(wrapper.find('[data-testid="pending-badge"]').exists()).toBe(false);
        });
    });

    describe('panel', () => {
        it('is a region named after its title', async () => {
            await mountSidebar();

            expect(panel().element.tagName).toBe('ASIDE');
            expect(panel().attributes('aria-labelledby')).toBe(panel().find('h2').attributes('id'));
        });

        it('closes from its named close button', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);

            await panel().find('button[aria-label="Fermer le panneau des tâches"]').trigger('click');

            expect(taskStore.closeSidebar).toHaveBeenCalled();
        });

        it('closes on Escape', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);

            await panel().trigger('keydown', { key: 'Escape' });

            expect(taskStore.closeSidebar).toHaveBeenCalled();
        });

        it('closes from the backdrop of narrow screens', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);

            await wrapper.find('[data-testid="sidebar-backdrop"]').trigger('click');

            expect(taskStore.closeSidebar).toHaveBeenCalled();
        });
    });

    describe('characters', () => {
        it('fetches the characters of an authenticated visitor on mount', async () => {
            await mountSidebar();

            expect(useCharacterStore(wrapper.vm.$pinia).fetchUserCharacters).toHaveBeenCalled();
        });

        it('fetches nothing when the characters are already loaded', async () => {
            await mountSidebar({ character: { userCharacters: [{ name: 'Arthas', realmSlug: 'hyjal', realm: 'Hyjal' }] } });

            expect(useCharacterStore(wrapper.vm.$pinia).fetchUserCharacters).not.toHaveBeenCalled();
        });

        it('invites to open a character sheet when there is nothing to show', async () => {
            await mountSidebar({ tasks: { tasks: [] } });

            expect(wrapper.text()).toContain('Ouvrez la fiche d’un de vos personnages');
            expect(sections()).toHaveLength(0);
        });

        it('lists one section per character holding tasks', async () => {
            await mountSidebar({ tasks: { tasks: [makeTask(), makeTask({ id: 2 }), makeTask({ id: 3, character_name: 'jaina' })] } });

            expect(sections()).toHaveLength(2);
        });

        it('puts first the character of an owned sheet, named after the sheet', async () => {
            __page.url = '/character/kazzak/thrall';
            __page.props = { isOwner: true, character: { name: 'Thrall', realm: 'Kazzak', avatarUrl: 'https://render.example/thrall.jpg' } };

            await mountSidebar();

            expect(sections()).toHaveLength(2);
            expect(sections()[0].text()).toContain('Thrall');
            expect(sections()[0].text()).toContain('Kazzak');
            expect(sections()[0].find('img').attributes('src')).toBe('https://render.example/thrall.jpg');
        });

        it('never offers the sheet of another player', async () => {
            __page.url = '/character/kazzak/thrall';
            __page.props = { isOwner: false, character: { name: 'Thrall', realm: 'Kazzak' } };

            await mountSidebar();

            expect(sections()).toHaveLength(1);
            expect(wrapper.text()).not.toContain('Thrall');
        });

        it('does not duplicate the character of the current sheet', async () => {
            __page.url = '/character/hyjal/arthas';
            __page.props = { isOwner: true, character: { name: 'Arthas', realm: 'Hyjal' } };

            await mountSidebar();

            expect(sections()).toHaveLength(1);
        });

        it('names a character after the account list', async () => {
            await mountSidebar({
                character: { userCharacters: [{ name: 'Arthas', realmSlug: 'hyjal', realm: 'Hyjal', avatarUrl: 'https://render.example/arthas.jpg' }] },
            });

            expect(sections()[0].text()).toContain('Arthas');
            expect(sections()[0].text()).toContain('Hyjal');
            expect(wrapper.find('img').attributes('src')).toBe('https://render.example/arthas.jpg');
        });

        it('falls back to the slugs and the initial without any detail', async () => {
            await mountSidebar();

            expect(wrapper.find('img').exists()).toBe(false);
            expect(sections()[0].text()).toContain('Arthas');
            expect(sections()[0].text()).toContain('hyjal');
        });

        it('expands and collapses a character, reflecting it', async () => {
            await mountSidebar();

            expect(headers()[0].attributes('aria-expanded')).toBe('false');
            expect(taskItems()).toHaveLength(0);

            await expandFirst();

            expect(headers()[0].attributes('aria-expanded')).toBe('true');
            expect(taskItems()).toHaveLength(1);

            await expandFirst();

            expect(taskItems()).toHaveLength(0);
        });

        it('expands each character independently', async () => {
            await mountSidebar({ tasks: { tasks: [makeTask(), makeTask({ id: 2, character_name: 'jaina' })] } });

            await expandFirst();

            expect(taskItems()).toHaveLength(1);
        });
    });

    describe('tasks', () => {
        it('checks a task through a labelled checkbox', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);
            await expandFirst();

            const checkbox = wrapper.find('[data-testid="task-checkbox"]');

            expect(checkbox.attributes('type')).toBe('checkbox');
            expect(wrapper.find(`label[for="${checkbox.attributes('id')}"]`).text()).toBe('Coffre hebdomadaire');

            await checkbox.trigger('change');

            expect(taskStore.toggleTask).toHaveBeenCalledWith(1);
        });

        it('reflects a completed task in its checkbox', async () => {
            await mountSidebar({ tasks: { tasks: [makeTask({ is_completed: true })] } });
            await expandFirst();

            expect(wrapper.find('[data-testid="task-checkbox"]').element.checked).toBe(true);
        });

        it('deletes a task from a named button, always visible', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);
            await expandFirst();

            const remove = wrapper.find('[data-testid="delete-task-btn"]');

            expect(remove.attributes('aria-label')).toBe('Supprimer la tâche « Coffre hebdomadaire »');
            expect(remove.classes()).not.toContain('opacity-0');

            await remove.trigger('click');

            expect(taskStore.deleteTask).toHaveBeenCalledWith(1);
        });

        it('writes the reset period in full', async () => {
            await mountSidebar({
                tasks: { tasks: [makeTask({ id: 1, reset_type: 'daily' }), makeTask({ id: 2, reset_type: 'weekly' }), makeTask({ id: 3, reset_type: 'monthly' })] },
            });
            await expandFirst();

            expect(taskItems().map((item) => item.find('[data-testid="task-frequency"]').text())).toEqual(['Quotidienne', 'Hebdomadaire', 'Mensuelle']);
        });
    });

    describe('creation form', () => {
        async function openForm() {
            await expandFirst();
            await wrapper.find('[data-testid="add-task-btn"]').trigger('click');
        }

        it('opens on demand', async () => {
            await mountSidebar();
            await expandFirst();

            expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(false);

            await wrapper.find('[data-testid="add-task-btn"]').trigger('click');

            expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(true);
        });

        it('labels each of its fields', async () => {
            await mountSidebar();
            await openForm();

            const name = wrapper.find('[data-testid="task-name-input"]');
            const frequency = wrapper.find('[data-testid="task-reset-select"]');

            expect(wrapper.find(`label[for="${name.attributes('id')}"]`).text()).toBe('Nom de la tâche');
            expect(wrapper.find(`label[for="${frequency.attributes('id')}"]`).text()).toBe('Fréquence');
            expect(frequency.findAll('option').map((option) => option.text())).toEqual(['Quotidienne', 'Hebdomadaire', 'Mensuelle']);
        });

        it('creates a task and closes', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);
            await openForm();

            await wrapper.find('[data-testid="task-name-input"]').setValue('  Donjon mythique  ');
            await wrapper.find('[data-testid="task-reset-select"]').setValue('monthly');
            await wrapper.find('[data-testid="task-form"]').trigger('submit');

            expect(taskStore.createTask).toHaveBeenCalledWith('hyjal', 'arthas', 'Donjon mythique', 'monthly');
            expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(false);
        });

        it('refuses a task without a name', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);
            await openForm();

            await wrapper.find('[data-testid="task-name-input"]').setValue('   ');
            await wrapper.find('[data-testid="task-form"]').trigger('submit');

            expect(taskStore.createTask).not.toHaveBeenCalled();
            expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(true);
        });

        it('closes without creating anything', async () => {
            await mountSidebar();
            const taskStore = useTaskStore(wrapper.vm.$pinia);
            await openForm();

            await wrapper.findAll('[data-testid="task-form"] button').find((button) => button.text() === 'Annuler').trigger('click');

            expect(taskStore.createTask).not.toHaveBeenCalled();
            expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(false);
        });
    });

    describe('programmatic opening', () => {
        it('expands the requested character, opens its form and focuses the name', async () => {
            await mountSidebar({ tasks: { tasks: [makeTask(), makeTask({ id: 2, character_name: 'jaina' })] } });
            const taskStore = useTaskStore(wrapper.vm.$pinia);

            taskStore.composeRequest = { realm_slug: 'hyjal', character_name: 'jaina', id: 1 };
            await nextTick();
            await nextTick();

            const section = sections().find((item) => item.text().includes('Jaina'));

            expect(section.find('[data-testid="character-header"]').attributes('aria-expanded')).toBe('true');
            expect(section.find('[data-testid="task-form"]').exists()).toBe(true);
            expect(document.activeElement).toBe(section.find('[data-testid="task-name-input"]').element);
        });

        it('lists a requested character that holds no task yet', async () => {
            __page.url = '/character/kazzak/thrall';
            __page.props = { isOwner: true, character: { name: 'Thrall', realm: 'Kazzak' } };
            await mountSidebar({ tasks: { tasks: [] } });
            const taskStore = useTaskStore(wrapper.vm.$pinia);

            taskStore.composeRequest = { realm_slug: 'kazzak', character_name: 'thrall', id: 1 };
            await nextTick();
            await nextTick();

            expect(sections()[0].find('[data-testid="task-form"]').exists()).toBe(true);
        });
    });
});
