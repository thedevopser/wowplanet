import { defineStore, acceptHMRUpdate } from 'pinia';
import axios from 'axios';

const SIDEBAR_KEY = 'wowplanet-tasks-sidebar';

function persistSidebar(open) {
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(SIDEBAR_KEY, String(open));
    }
}

export const useTaskStore = defineStore('tasks', {
    state: () => ({
        tasks: [],
        loading: false,
        sidebarOpen: typeof localStorage !== 'undefined' && localStorage.getItem(SIDEBAR_KEY) === 'true',
        composeRequest: null,
    }),

    getters: {
        charactersWithTasks(state) {
            const seen = new Set();
            const result = [];
            for (const task of state.tasks) {
                const key = `${task.realm_slug}|${task.character_name}`;
                if (!seen.has(key)) {
                    seen.add(key);
                    result.push({ realm_slug: task.realm_slug, character_name: task.character_name });
                }
            }
            return result;
        },

        totalPendingCount(state) {
            return state.tasks.filter(t => !t.is_completed).length;
        },

        characterTasks(state) {
            return (realmSlug, characterName) =>
                state.tasks
                    .filter(t => t.realm_slug === realmSlug && t.character_name === characterName)
                    .sort((a, b) => {
                    const order = { daily: 0, weekly: 1, monthly: 2 };
                    return (order[a.reset_type] ?? 3) - (order[b.reset_type] ?? 3);
                });
        },

        pendingCount(state) {
            return (realmSlug, characterName) =>
                state.tasks.filter(
                    t => t.realm_slug === realmSlug && t.character_name === characterName && !t.is_completed
                ).length;
        },
    },

    actions: {
        async fetchTasks() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/character-tasks');
                this.tasks = data;
                this.applyResets();
            } finally {
                this.loading = false;
            }
        },

        async createTask(realmSlug, characterName, taskName, resetType) {
            const { data } = await axios.post('/api/character-tasks', {
                realm_slug: realmSlug,
                character_name: characterName,
                name: taskName,
                reset_type: resetType,
            });
            this.tasks.push(data);
        },

        async toggleTask(taskId) {
            const { data } = await axios.put(`/api/character-tasks/${taskId}`);
            const index = this.tasks.findIndex(t => t.id === taskId);
            if (index !== -1) {
                this.tasks[index] = data;
            }
        },

        async deleteTask(taskId) {
            await axios.delete(`/api/character-tasks/${taskId}`);
            this.tasks = this.tasks.filter(t => t.id !== taskId);
        },

        applyResets() {
            const now = new Date();
            const thresholds = {
                daily: this._getDailyThreshold(now),
                weekly: this._getWeeklyThreshold(now),
                monthly: this._getMonthlyThreshold(now),
            };

            for (const task of this.tasks) {
                if (!task.is_completed || !task.completed_at) continue;

                const completedAt = new Date(task.completed_at);
                const threshold = thresholds[task.reset_type];

                if (completedAt < threshold) {
                    task.is_completed = false;
                    task.completed_at = null;
                    // Fire-and-forget reset to API
                    axios.put(`/api/character-tasks/${task.id}`);
                }
            }
        },

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            persistSidebar(this.sidebarOpen);
        },

        closeSidebar() {
            this.sidebarOpen = false;
            persistSidebar(false);
        },

        // Opens the panel on a character with its creation form ready: the entry point of
        // the "Ajouter une tâche" action of a character sheet. Each call is a new request,
        // so asking twice for the same character focuses the field again.
        openFor(realmSlug, characterName) {
            this.sidebarOpen = true;
            persistSidebar(true);
            this.composeRequest = {
                realm_slug: realmSlug,
                character_name: characterName.toLowerCase(),
                id: (this.composeRequest?.id ?? 0) + 1,
            };
        },

        _getDailyThreshold(now) {
            const threshold = new Date(now);
            threshold.setHours(5, 0, 0, 0);
            if (now < threshold) {
                threshold.setDate(threshold.getDate() - 1);
            }
            return threshold;
        },

        _getWeeklyThreshold(now) {
            const threshold = new Date(now);
            threshold.setHours(5, 0, 0, 0);
            // Go back to last Wednesday
            const day = threshold.getDay(); // 0=Sun, 3=Wed
            const daysBack = (day + 4) % 7; // days since last Wednesday
            threshold.setDate(threshold.getDate() - daysBack);
            if (now < threshold) {
                threshold.setDate(threshold.getDate() - 7);
            }
            return threshold;
        },

        _getMonthlyThreshold(now) {
            const threshold = new Date(now.getFullYear(), now.getMonth(), 1, 5, 0, 0, 0);
            if (now < threshold) {
                threshold.setMonth(threshold.getMonth() - 1);
            }
            return threshold;
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useTaskStore, import.meta.hot));
}
