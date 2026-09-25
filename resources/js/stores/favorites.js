import { defineStore, acceptHMRUpdate } from 'pinia';
import axios from 'axios';

export const MAX_FAVORITES = 3;

const favoriteKey = (realmSlug, characterName) =>
    `${String(realmSlug).toLowerCase()}|${String(characterName).toLowerCase()}`;

export const useFavoriteStore = defineStore('favorites', {
    state: () => ({
        favorites: [],
        loading: false,
    }),

    getters: {
        favoriteCount: (state) => state.favorites.length,

        isFull: (state) => state.favorites.length >= MAX_FAVORITES,

        favoriteKeys: (state) =>
            new Set(state.favorites.map(f => favoriteKey(f.realm_slug, f.character_name))),

        isFavorite() {
            return (realmSlug, characterName) => this.favoriteKeys.has(favoriteKey(realmSlug, characterName));
        },
    },

    actions: {
        async fetchFavorites() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/character-favorites');
                this.favorites = data;
            } finally {
                this.loading = false;
            }
        },

        async addFavorite(realmSlug, characterName) {
            if (this.isFull || this.isFavorite(realmSlug, characterName)) return;

            const optimistic = {
                id: null,
                realm_slug: String(realmSlug).toLowerCase(),
                character_name: String(characterName).toLowerCase(),
                sort_order: this.favorites.length,
            };
            const key = favoriteKey(optimistic.realm_slug, optimistic.character_name);
            this.favorites.push(optimistic);

            try {
                const { data } = await axios.post('/api/character-favorites', {
                    realm_slug: optimistic.realm_slug,
                    character_name: optimistic.character_name,
                });
                const index = this.favorites.findIndex(
                    f => favoriteKey(f.realm_slug, f.character_name) === key
                );
                if (index !== -1) {
                    this.favorites[index] = data;
                }
            } catch (error) {
                this.favorites = this.favorites.filter(
                    f => favoriteKey(f.realm_slug, f.character_name) !== key
                );
                throw error;
            }
        },

        async removeFavorite(realmSlug, characterName) {
            const key = favoriteKey(realmSlug, characterName);
            const previous = this.favorites;
            this.favorites = this.favorites.filter(
                f => favoriteKey(f.realm_slug, f.character_name) !== key
            );

            try {
                await axios.delete(`/api/character-favorites/${String(realmSlug).toLowerCase()}/${String(characterName).toLowerCase()}`);
            } catch (error) {
                this.favorites = previous;
                throw error;
            }
        },

        async toggleFavorite(realmSlug, characterName) {
            if (this.isFavorite(realmSlug, characterName)) {
                await this.removeFavorite(realmSlug, characterName);
            } else {
                await this.addFavorite(realmSlug, characterName);
            }
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useFavoriteStore, import.meta.hot));
}
