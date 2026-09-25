import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import axios from 'axios';
import { useFavoriteStore, MAX_FAVORITES } from './favorites';

vi.mock('axios');

const favorite = (realm, name, sortOrder = 0) => ({
    id: sortOrder + 1,
    realm_slug: realm,
    character_name: name,
    sort_order: sortOrder,
});

describe('favorites store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    // ─── Initial state ────────────────────────────────────

    it('has correct initial state', () => {
        const store = useFavoriteStore();

        expect(store.favorites).toEqual([]);
        expect(store.loading).toBe(false);
        expect(store.favoriteCount).toBe(0);
        expect(store.isFull).toBe(false);
    });

    // ─── Getters ──────────────────────────────────────────

    it('isFavorite matches regardless of case', () => {
        const store = useFavoriteStore();
        store.favorites = [favorite('hyjal', 'thrall')];

        expect(store.isFavorite('hyjal', 'thrall')).toBe(true);
        expect(store.isFavorite('Hyjal', 'Thrall')).toBe(true);
        expect(store.isFavorite('dalaran', 'thrall')).toBe(false);
    });

    it('isFull becomes true at the maximum', () => {
        const store = useFavoriteStore();
        store.favorites = [favorite('hyjal', 'a', 0), favorite('hyjal', 'b', 1)];
        expect(store.isFull).toBe(false);

        store.favorites.push(favorite('hyjal', 'c', 2));
        expect(store.favoriteCount).toBe(MAX_FAVORITES);
        expect(store.isFull).toBe(true);
    });

    // ─── Fetch ────────────────────────────────────────────

    it('fetchFavorites loads favorites from the API', async () => {
        axios.get.mockResolvedValue({ data: [favorite('hyjal', 'thrall')] });
        const store = useFavoriteStore();

        await store.fetchFavorites();

        expect(axios.get).toHaveBeenCalledWith('/api/character-favorites');
        expect(store.favorites).toHaveLength(1);
        expect(store.loading).toBe(false);
    });

    it('fetchFavorites resets loading when the request fails', async () => {
        axios.get.mockRejectedValue(new Error('boom'));
        const store = useFavoriteStore();

        await expect(store.fetchFavorites()).rejects.toThrow('boom');
        expect(store.loading).toBe(false);
    });

    // ─── Add ──────────────────────────────────────────────

    it('addFavorite posts lowercased values and stores the response', async () => {
        axios.post.mockResolvedValue({ data: favorite('hyjal', 'thrall') });
        const store = useFavoriteStore();

        await store.addFavorite('Hyjal', 'Thrall');

        expect(axios.post).toHaveBeenCalledWith('/api/character-favorites', {
            realm_slug: 'hyjal',
            character_name: 'thrall',
        });
        expect(store.favorites).toEqual([favorite('hyjal', 'thrall')]);
    });

    it('addFavorite rolls back the optimistic entry on failure', async () => {
        axios.post.mockRejectedValue(new Error('server down'));
        const store = useFavoriteStore();

        await expect(store.addFavorite('hyjal', 'thrall')).rejects.toThrow('server down');
        expect(store.favorites).toEqual([]);
    });

    it('addFavorite does nothing when already full', async () => {
        const store = useFavoriteStore();
        store.favorites = [favorite('hyjal', 'a', 0), favorite('hyjal', 'b', 1), favorite('hyjal', 'c', 2)];

        await store.addFavorite('hyjal', 'd');

        expect(axios.post).not.toHaveBeenCalled();
        expect(store.favorites).toHaveLength(MAX_FAVORITES);
    });

    it('addFavorite does nothing when already a favorite', async () => {
        const store = useFavoriteStore();
        store.favorites = [favorite('hyjal', 'thrall')];

        await store.addFavorite('hyjal', 'thrall');

        expect(axios.post).not.toHaveBeenCalled();
        expect(store.favorites).toHaveLength(1);
    });

    // ─── Remove ───────────────────────────────────────────

    it('removeFavorite deletes and drops the entry', async () => {
        axios.delete.mockResolvedValue({});
        const store = useFavoriteStore();
        store.favorites = [favorite('hyjal', 'thrall'), favorite('dalaran', 'jaina', 1)];

        await store.removeFavorite('Hyjal', 'Thrall');

        expect(axios.delete).toHaveBeenCalledWith('/api/character-favorites/hyjal/thrall');
        expect(store.favorites.map(f => f.character_name)).toEqual(['jaina']);
    });

    it('removeFavorite restores the list on failure', async () => {
        axios.delete.mockRejectedValue(new Error('nope'));
        const store = useFavoriteStore();
        store.favorites = [favorite('hyjal', 'thrall')];

        await expect(store.removeFavorite('hyjal', 'thrall')).rejects.toThrow('nope');
        expect(store.favorites).toHaveLength(1);
    });

    // ─── Toggle ───────────────────────────────────────────

    it('toggleFavorite adds when absent and removes when present', async () => {
        axios.post.mockResolvedValue({ data: favorite('hyjal', 'thrall') });
        axios.delete.mockResolvedValue({});
        const store = useFavoriteStore();

        await store.toggleFavorite('hyjal', 'thrall');
        expect(store.isFavorite('hyjal', 'thrall')).toBe(true);

        await store.toggleFavorite('hyjal', 'thrall');
        expect(store.isFavorite('hyjal', 'thrall')).toBe(false);
    });
});
