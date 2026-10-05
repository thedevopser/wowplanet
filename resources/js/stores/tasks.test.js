import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import axios from 'axios';
import { useTaskStore } from './tasks';

vi.mock('axios');

describe('tasks store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        localStorage.clear();
    });

    // ─── Initial state ────────────────────────────────────

    it('has correct initial state', () => {
        const store = useTaskStore();

        expect(store.tasks).toEqual([]);
        expect(store.loading).toBe(false);
        expect(store.sidebarOpen).toBe(false);
    });

    // ─── Getters ──────────────────────────────────────────

    it('characterTasks filters by realm and character', () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task A', reset_type: 'daily', is_completed: false },
            { id: 2, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task B', reset_type: 'daily', is_completed: false },
            { id: 3, realm_slug: 'dalaran', character_name: 'jaina', name: 'Task C', reset_type: 'daily', is_completed: false },
        ];

        const thrallTasks = store.characterTasks('hyjal', 'thrall');
        expect(thrallTasks).toHaveLength(2);
        expect(thrallTasks.map(t => t.name)).toEqual(['Task A', 'Task B']);

        const jainaTasks = store.characterTasks('dalaran', 'jaina');
        expect(jainaTasks).toHaveLength(1);
    });

    it('characterTasks sorts daily before weekly before monthly', () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Raid hebdo', reset_type: 'weekly', is_completed: false },
            { id: 2, realm_slug: 'hyjal', character_name: 'thrall', name: 'Quête daily', reset_type: 'daily', is_completed: false },
            { id: 3, realm_slug: 'hyjal', character_name: 'thrall', name: 'Objectif mensuel', reset_type: 'monthly', is_completed: false },
            { id: 4, realm_slug: 'hyjal', character_name: 'thrall', name: 'Donjon daily', reset_type: 'daily', is_completed: false },
        ];

        const tasks = store.characterTasks('hyjal', 'thrall');
        expect(tasks.map(t => t.reset_type)).toEqual(['daily', 'daily', 'weekly', 'monthly']);
    });

    it('charactersWithTasks returns unique character list', () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'A' },
            { id: 2, realm_slug: 'hyjal', character_name: 'thrall', name: 'B' },
            { id: 3, realm_slug: 'dalaran', character_name: 'jaina', name: 'C' },
        ];

        expect(store.charactersWithTasks).toHaveLength(2);
        expect(store.charactersWithTasks).toContainEqual({ realm_slug: 'hyjal', character_name: 'thrall' });
        expect(store.charactersWithTasks).toContainEqual({ realm_slug: 'dalaran', character_name: 'jaina' });
    });

    it('totalPendingCount counts incomplete tasks', () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, is_completed: false },
            { id: 2, is_completed: true },
            { id: 3, is_completed: false },
        ];

        expect(store.totalPendingCount).toBe(2);
    });

    it('pendingCount counts incomplete tasks for a specific character', () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, realm_slug: 'hyjal', character_name: 'thrall', is_completed: false },
            { id: 2, realm_slug: 'hyjal', character_name: 'thrall', is_completed: true },
            { id: 3, realm_slug: 'dalaran', character_name: 'jaina', is_completed: false },
        ];

        expect(store.pendingCount('hyjal', 'thrall')).toBe(1);
    });

    // ─── Actions ──────────────────────────────────────────

    it('fetchTasks loads tasks from API', async () => {
        const mockTasks = [
            { id: 1, name: 'Task A', is_completed: false, completed_at: null, reset_type: 'daily' },
        ];
        axios.get.mockResolvedValue({ data: mockTasks });

        const store = useTaskStore();
        await store.fetchTasks();

        expect(axios.get).toHaveBeenCalledWith('/api/character-tasks');
        expect(store.tasks).toEqual(mockTasks);
    });

    it('createTask posts to API and adds to store', async () => {
        const newTask = {
            id: 1,
            realm_slug: 'hyjal',
            character_name: 'thrall',
            name: 'Donjon hebdo',
            reset_type: 'weekly',
            is_completed: false,
        };
        axios.post.mockResolvedValue({ data: newTask });

        const store = useTaskStore();
        await store.createTask('hyjal', 'thrall', 'Donjon hebdo', 'weekly');

        expect(axios.post).toHaveBeenCalledWith('/api/character-tasks', {
            realm_slug: 'hyjal',
            character_name: 'thrall',
            name: 'Donjon hebdo',
            reset_type: 'weekly',
        });
        expect(store.tasks).toHaveLength(1);
        expect(store.tasks[0].name).toBe('Donjon hebdo');
    });

    it('toggleTask updates task via API and in store', async () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, name: 'Task A', is_completed: false, completed_at: null },
        ];

        axios.put.mockResolvedValue({ data: { id: 1, name: 'Task A', is_completed: true, completed_at: '2026-03-19T10:00:00Z' } });

        await store.toggleTask(1);

        expect(axios.put).toHaveBeenCalledWith('/api/character-tasks/1');
        expect(store.tasks[0].is_completed).toBe(true);
    });

    it('deleteTask removes task via API and from store', async () => {
        const store = useTaskStore();
        store.tasks = [
            { id: 1, name: 'Task A' },
            { id: 2, name: 'Task B' },
        ];

        axios.delete.mockResolvedValue({});

        await store.deleteTask(1);

        expect(axios.delete).toHaveBeenCalledWith('/api/character-tasks/1');
        expect(store.tasks).toHaveLength(1);
        expect(store.tasks[0].id).toBe(2);
    });

    // ─── Reset logic ────────────────────────────────────

    describe('applyResets', () => {
        const completedTask = (resetType, completedAt) => ({
            id: 1,
            reset_type: resetType,
            is_completed: true,
            completed_at: completedAt.toISOString(),
        });

        beforeEach(() => {
            vi.useFakeTimers();
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('resets a daily task completed before the 5am reset of the day', () => {
            vi.setSystemTime(new Date(2026, 9, 5, 10, 0));
            const store = useTaskStore();
            store.tasks = [completedTask('daily', new Date(2026, 9, 4, 22, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(false);
            expect(store.tasks[0].completed_at).toBeNull();
            expect(axios.put).toHaveBeenCalledWith('/api/character-tasks/1');
        });

        it('keeps a daily task completed since the 5am reset of the day', () => {
            vi.setSystemTime(new Date(2026, 9, 5, 12, 0));
            const store = useTaskStore();
            store.tasks = [completedTask('daily', new Date(2026, 9, 5, 10, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(true);
            expect(axios.put).not.toHaveBeenCalled();
        });

        it('keeps a daily task completed the evening before while the 5am reset has not come', () => {
            vi.setSystemTime(new Date(2026, 9, 5, 4, 45));
            const store = useTaskStore();
            store.tasks = [completedTask('daily', new Date(2026, 9, 4, 22, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(true);
        });

        it('resets a daily task older than the previous 5am reset when read before 5am', () => {
            vi.setSystemTime(new Date(2026, 9, 5, 4, 45));
            const store = useTaskStore();
            store.tasks = [completedTask('daily', new Date(2026, 9, 4, 4, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(false);
        });

        it('leaves an incomplete task untouched', () => {
            vi.setSystemTime(new Date(2026, 9, 5, 10, 0));
            const store = useTaskStore();
            store.tasks = [{ id: 1, reset_type: 'daily', is_completed: false, completed_at: null }];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(false);
            expect(axios.put).not.toHaveBeenCalled();
        });

        it('resets a monthly task completed before the 5am reset of the 1st', () => {
            vi.setSystemTime(new Date(2026, 9, 20, 10, 0));
            const store = useTaskStore();
            store.tasks = [completedTask('monthly', new Date(2026, 8, 15, 10, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(false);
            expect(store.tasks[0].completed_at).toBeNull();
        });

        it('keeps a monthly task completed since the 5am reset of the 1st', () => {
            vi.setSystemTime(new Date(2026, 9, 20, 10, 0));
            const store = useTaskStore();
            store.tasks = [completedTask('monthly', new Date(2026, 9, 2, 10, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(true);
        });

        it('keeps a monthly task of the previous month while the 5am reset of the 1st has not come', () => {
            vi.setSystemTime(new Date(2026, 9, 1, 4, 45));
            const store = useTaskStore();
            store.tasks = [completedTask('monthly', new Date(2026, 8, 15, 10, 0))];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(true);
        });
    });

    // ─── Sidebar persistence ─────────────────────────────

    it('persists sidebarOpen state in localStorage', () => {
        const store = useTaskStore();
        store.toggleSidebar();

        expect(store.sidebarOpen).toBe(true);
        expect(localStorage.getItem('wowplanet-tasks-sidebar')).toBe('true');
    });

    // ─── Programmatic opening ────────────────────────────

    it('opens the panel on a character, its form requested', () => {
        const store = useTaskStore();

        store.openFor('hyjal', 'Thrall');

        expect(store.sidebarOpen).toBe(true);
        expect(store.composeRequest).toEqual({ realm_slug: 'hyjal', character_name: 'thrall', id: 1 });
        expect(localStorage.getItem('wowplanet-tasks-sidebar')).toBe('true');
    });

    it('renews the request when asked twice for the same character', () => {
        const store = useTaskStore();

        store.openFor('hyjal', 'thrall');
        store.openFor('hyjal', 'thrall');

        expect(store.composeRequest.id).toBe(2);
    });

    it('closes the panel', () => {
        const store = useTaskStore();
        store.openFor('hyjal', 'thrall');

        store.closeSidebar();

        expect(store.sidebarOpen).toBe(false);
        expect(localStorage.getItem('wowplanet-tasks-sidebar')).toBe('false');
    });
});
