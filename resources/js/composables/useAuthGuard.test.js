import { vi, describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import axios from 'axios';
import { useCharacterStore } from '../stores/character';
import { useAuthGuard } from './useAuthGuard';

vi.mock('axios', () => ({
    default: {
        interceptors: {
            response: {
                use: vi.fn(),
            },
        },
    },
}));

describe('useAuthGuard', () => {
    let interceptorErrorHandler;

    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();

        axios.interceptors.response.use.mockImplementation((_onFulfilled, onRejected) => {
            interceptorErrorHandler = onRejected;
        });
    });

    it('enregistre un intercepteur de réponse Axios', () => {
        useAuthGuard();

        expect(axios.interceptors.response.use).toHaveBeenCalledOnce();
    });

    it('appelle handleSessionExpired sur un 401 quand l\'utilisateur est authentifié', async () => {
        const store = useCharacterStore();
        store.isAuthenticated = true;
        useAuthGuard();

        const error = { response: { status: 401 } };
        await expect(interceptorErrorHandler(error)).rejects.toEqual(error);

        expect(store.sessionExpired).toBe(true);
        expect(store.isAuthenticated).toBe(false);
    });

    it('ne déclenche rien sur un 401 quand l\'utilisateur n\'est pas authentifié', async () => {
        const store = useCharacterStore();
        store.isAuthenticated = false;
        useAuthGuard();

        const error = { response: { status: 401 } };
        await expect(interceptorErrorHandler(error)).rejects.toEqual(error);

        expect(store.sessionExpired).toBe(false);
    });

    it('ne déclenche rien sur un 403', async () => {
        const store = useCharacterStore();
        store.isAuthenticated = true;
        useAuthGuard();

        const error = { response: { status: 403 } };
        await expect(interceptorErrorHandler(error)).rejects.toEqual(error);

        expect(store.sessionExpired).toBe(false);
    });

    it('ne déclenche rien sur un 500', async () => {
        const store = useCharacterStore();
        store.isAuthenticated = true;
        useAuthGuard();

        const error = { response: { status: 500 } };
        await expect(interceptorErrorHandler(error)).rejects.toEqual(error);

        expect(store.sessionExpired).toBe(false);
    });

    it('rejette l\'erreur dans tous les cas (pas de swallow)', async () => {
        const store = useCharacterStore();
        store.isAuthenticated = true;
        useAuthGuard();

        const error = { response: { status: 401 } };

        await expect(interceptorErrorHandler(error)).rejects.toEqual(error);
    });
});
