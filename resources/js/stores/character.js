import { defineStore, acceptHMRUpdate } from 'pinia';
import axios from 'axios';

export const useCharacterStore = defineStore('character', {
    state: () => ({
        character: null,
        error: null,
        isAuthenticated: false,
        isAdmin: false,
        battletag: '',
        sessionExpired: false,
        userCharacters: [],
        classIcons: {},
        loadingCharacters: false,
        crossCharacter: null,
        crossCharacterStatus: 'idle',
        crossCharacterProgress: null,
        _crossCharQuestSet: null,
        _crossCharAchievementSet: null,
        _crossCharRecipeSet: null,
        expansions: [
            { id: 0, name: 'Classic' },
            { id: 1, name: 'The Burning Crusade' },
            { id: 2, name: 'Wrath of the Lich King' },
            { id: 3, name: 'Cataclysm' },
            { id: 4, name: 'Mists of Pandaria' },
            { id: 5, name: 'Warlords of Draenor' },
            { id: 6, name: 'Legion' },
            { id: 7, name: 'Battle for Azeroth' },
            { id: 8, name: 'Shadowlands' },
            { id: 9, name: 'Dragonflight' },
            { id: 10, name: 'The War Within' },
            { id: 11, name: 'Midnight' },
            // Ce que rien ne date. Le drapeau le garde hors des onglets où il n'a aucun
            // contenu, et hors des deux getters qui parlent des extensions réelles.
            { id: 99, name: 'Non classé', onlyWhenFilled: true },
        ],
    }),

    getters: {
        latestExpansionId: (state) => state.expansions.filter(e => !e.onlyWhenFilled).at(-1)?.id ?? 10,
        expansionNamesDesc: (state) => [...state.expansions].filter(e => !e.onlyWhenFilled).reverse().map(e => e.name),
        crossCharQuestIds: (state) => {
            if (!state.crossCharacter) return null;
            if (!state._crossCharQuestSet) {
                state._crossCharQuestSet = new Set(state.crossCharacter.completedQuestIds || []);
            }
            return state._crossCharQuestSet;
        },
        crossCharAchievementIds: (state) => {
            if (!state.crossCharacter) return null;
            if (!state._crossCharAchievementSet) {
                state._crossCharAchievementSet = new Set(state.crossCharacter.completedAchievementIds || []);
            }
            return state._crossCharAchievementSet;
        },
        crossCharRecipeIds: (state) => {
            if (!state.crossCharacter) return null;
            if (!state._crossCharRecipeSet) {
                state._crossCharRecipeSet = new Set(state.crossCharacter.completedRecipeIds || []);
            }
            return state._crossCharRecipeSet;
        },
    },

    actions: {
        // The server shares the auth state with every page, server render included:
        // reading it there keeps the header right from the first paint.
        applySharedAuth(auth) {
            this.isAuthenticated = auth?.isAuthenticated === true;
            this.isAdmin = auth?.isAdmin === true;
            this.battletag = typeof auth?.battletag === 'string' ? auth.battletag : '';
        },

        async fetchUserCharacters() {
            this.loadingCharacters = true;
            this.error = null;
            try {
                const response = await axios.get('/api/user/characters');
                this.userCharacters = response.data;
            } catch (err) {
                this.error = err.response?.data?.message || 'Impossible de récupérer vos personnages';
            } finally {
                this.loadingCharacters = false;
            }
        },

        async fetchClassIcons() {
            if (Object.keys(this.classIcons).length) return;
            try {
                const response = await axios.get('/api/class-icons');
                this.classIcons = response.data;
            } catch {
                // fallback: icons stay empty, UI will show initial letter
            }
        },

        handleSessionExpired() {
            if (!this.isAuthenticated) return;
            this.isAuthenticated = false;
            this.isAdmin = false;
            this.userCharacters = [];
            this.error = null;
            this.sessionExpired = true;
        },

        clearSessionExpired() {
            this.sessionExpired = false;
        },

        async logout() {
            try {
                await axios.post('/api/auth/logout');
            } catch {
                // ignore
            }
            this.isAuthenticated = false;
            this.isAdmin = false;
            this.userCharacters = [];
            this.character = null;
            this.crossCharacter = null;
            this.crossCharacterStatus = 'idle';
            this._crossCharQuestSet = null;
            this._crossCharAchievementSet = null;
            this._crossCharRecipeSet = null;
        },

        async computeCrossCharacter() {
            if (this.crossCharacterStatus === 'loading' || this.crossCharacterStatus === 'ready') return;
            this.crossCharacterStatus = 'loading';
            try {
                const response = await axios.get('/api/account/cross-character');
                const { status, data, jobId } = response.data;

                if (status === 'ready' && data) return this._setCrossCharacterData(data);
                if (status === 'computing' && jobId) return this._pollCrossCharacterJob(jobId);
            } catch {
                this.crossCharacterStatus = 'error';
            }
        },

        _pollCrossCharacterJob(jobId) {
            const poll = async () => {
                const res = await axios.get(`/api/account/cross-character/${jobId}`).catch(() => null);
                const status = res?.data?.status;

                if (status === 'completed') return this.loadCrossCharacterData();
                if (status === 'failed' || status === 'not_found' || !res) {
                    this.crossCharacterStatus = 'error';
                    return;
                }

                setTimeout(poll, 3000);
            };
            setTimeout(poll, 3000);
        },

        async loadCrossCharacterData() {
            if (this.crossCharacterStatus === 'ready') return;
            try {
                const response = await axios.get('/api/account/cross-character-data');
                if (response.data.status === 'ready' && response.data.data) {
                    this._setCrossCharacterData(response.data.data);
                } else {
                    this.crossCharacterStatus = 'not_available';
                }
            } catch {
                this.crossCharacterStatus = 'not_available';
            }
        },

        _setCrossCharacterData(data) {
            this.crossCharacter = data;
            this.crossCharacterStatus = 'ready';
            this._crossCharQuestSet = null;
            this._crossCharAchievementSet = null;
            this._crossCharRecipeSet = null;
        },

        isQuestCompletedElsewhere(questId) {
            const set = this.crossCharQuestIds;
            return set ? set.has(questId) : false;
        },

        isAchievementCompletedElsewhere(achievementId) {
            const set = this.crossCharAchievementIds;
            return set ? set.has(achievementId) : false;
        },

        isRecipeKnownElsewhere(recipeId) {
            const set = this.crossCharRecipeIds;
            return set ? set.has(recipeId) : false;
        },

        getQuestOwner(questId) {
            if (!this.crossCharacter?.questOwners) return null;
            return this.crossCharacter.questOwners[questId] || null;
        },

        getAchievementOwner(achievementId) {
            if (!this.crossCharacter?.achievementOwners) return null;
            return this.crossCharacter.achievementOwners[achievementId] || null;
        },

        getRecipeOwner(recipeId) {
            if (!this.crossCharacter?.recipeOwners) return null;
            return this.crossCharacter.recipeOwners[recipeId] || null;
        },

        getBestFactionStanding(factionId) {
            if (!this.crossCharacter?.bestFactionStandings) return null;
            return this.crossCharacter.bestFactionStandings[factionId] || null;
        },

        getBestSkillPoints(profId, expId) {
            if (!this.crossCharacter?.skillPointOwners) return null;
            const prof = this.crossCharacter.skillPointOwners[profId];
            if (!prof) return null;
            return prof[expId] || null;
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useCharacterStore, import.meta.hot));
}
