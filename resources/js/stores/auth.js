import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        hydrated: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        isAdmin: (state) => state.user?.role === 'admin',
        isRecipient: (state) => state.user?.role === 'recipient',
    },

    actions: {
        async hydrate() {
            if (this.hydrated) return;
            try {
                const { data } = await http.get('/auth/me');
                this.user = data.user;
            } catch {
                this.user = null;
            } finally {
                this.hydrated = true;
            }
        },

        async login(credentials) {
            await ensureCsrf();
            const { data } = await http.post('/auth/login', credentials);
            this.user = data.user;
            this.hydrated = true;
            return data.user;
        },

        async logout() {
            try {
                await http.post('/auth/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
