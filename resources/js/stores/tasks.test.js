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

    describe.each(['Europe/Paris', 'America/New_York', 'Asia/Tokyo'])('applyResets, browser set to %s', (timezone) => {
        const utc = (year, month, day, hour, minute = 0) => new Date(Date.UTC(year, month - 1, day, hour, minute));

        const completedTask = (resetType, completedAt) => ({
            id: 1,
            reset_type: resetType,
            is_completed: true,
            completed_at: completedAt.toISOString(),
        });

        let browserTimezone;

        beforeEach(() => {
            browserTimezone = process.env.TZ;
            process.env.TZ = timezone;
            vi.useFakeTimers();
        });

        afterEach(() => {
            vi.useRealTimers();
            if (browserTimezone === undefined) {
                delete process.env.TZ;
            } else {
                process.env.TZ = browserTimezone;
            }
        });

        it.each([
            ['a daily task done just before 4am UTC, in summer', 'daily', utc(2026, 7, 1, 4), utc(2026, 7, 1, 3, 59)],
            ['a daily task done just before 4am UTC, in winter', 'daily', utc(2026, 1, 15, 4), utc(2026, 1, 15, 3, 59)],
            ['a daily task older than the previous reset, read before 4am UTC', 'daily', utc(2026, 7, 1, 3, 30), utc(2026, 6, 30, 3, 59)],
            ['a weekly task done just before Wednesday 4am UTC, in summer', 'weekly', utc(2026, 6, 24, 4), utc(2026, 6, 24, 3, 59)],
            ['a weekly task done just before Wednesday 4am UTC, in winter', 'weekly', utc(2026, 11, 20, 12), utc(2026, 11, 18, 3, 59)],
            ['a weekly task older than the previous Wednesday, read on Wednesday before 4am UTC', 'weekly', utc(2026, 11, 18, 3), utc(2026, 11, 11, 3, 59)],
            ['a monthly task done just before 4am UTC on the 1st, in summer', 'monthly', utc(2026, 7, 1, 4), utc(2026, 7, 1, 3, 59)],
            ['a monthly task of the previous month, in winter', 'monthly', utc(2026, 1, 20, 10), utc(2025, 12, 31, 23)],
        ])('resets %s', (_case, resetType, now, completedAt) => {
            vi.setSystemTime(now);
            const store = useTaskStore();
            store.tasks = [completedTask(resetType, completedAt)];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(false);
            expect(store.tasks[0].completed_at).toBeNull();
            expect(axios.put).toHaveBeenCalledWith('/api/character-tasks/1');
        });

        it.each([
            ['a daily task done at 4am UTC, in summer', 'daily', utc(2026, 7, 1, 12), utc(2026, 7, 1, 4)],
            ['a daily task done the day before while 4am UTC has not come', 'daily', utc(2026, 7, 1, 3, 59), utc(2026, 6, 30, 4)],
            ['a weekly task done at Wednesday 4am UTC, in summer', 'weekly', utc(2026, 6, 24, 4), utc(2026, 6, 24, 4)],
            ['a weekly task done since Wednesday 4am UTC, in winter', 'weekly', utc(2026, 11, 20, 12), utc(2026, 11, 18, 4)],
            ['a weekly task of the previous week while Wednesday 4am UTC has not come', 'weekly', utc(2026, 11, 18, 3), utc(2026, 11, 11, 4)],
            ['a weekly task read on Tuesday evening UTC', 'weekly', utc(2026, 11, 17, 23), utc(2026, 11, 11, 5)],
            ['a monthly task done since 4am UTC on the 1st, in winter', 'monthly', utc(2026, 1, 20, 10), utc(2026, 1, 1, 4)],
            ['a monthly task of the previous month while 4am UTC on the 1st has not come', 'monthly', utc(2026, 7, 1, 3, 59), utc(2026, 6, 1, 4)],
        ])('keeps %s', (_case, resetType, now, completedAt) => {
            vi.setSystemTime(now);
            const store = useTaskStore();
            store.tasks = [completedTask(resetType, completedAt)];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(true);
            expect(axios.put).not.toHaveBeenCalled();
        });

        it('leaves an incomplete task untouched', () => {
            vi.setSystemTime(utc(2026, 7, 1, 12));
            const store = useTaskStore();
            store.tasks = [{ id: 1, reset_type: 'daily', is_completed: false, completed_at: null }];

            store.applyResets();

            expect(store.tasks[0].is_completed).toBe(false);
            expect(axios.put).not.toHaveBeenCalled();
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
