import axios from 'axios';
import { useCharacterStore } from '../stores/character';

export function useAuthGuard() {
    const store = useCharacterStore();

    axios.interceptors.response.use(
        response => response,
        error => {
            if (error.response?.status === 401 && store.isAuthenticated) {
                store.handleSessionExpired();
            }
            return Promise.reject(error);
        }
    );
}
