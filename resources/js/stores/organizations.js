import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';

export const useOrganizationsStore = defineStore('organizations', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', status: '', page: 1 },
    }),

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get('/admin/organizations', { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`/admin/organizations/${uuid}`);
            return data;
        },

        async create(formData) {
            await ensureCsrf();
            const { data } = await http.post('/admin/organizations', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        async update(uuid, formData) {
            await ensureCsrf();
            formData.append('_method', 'PUT');
            const { data } = await http.post(`/admin/organizations/${uuid}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`/admin/organizations/${uuid}`);
            this.items = this.items.filter((o) => o.uuid !== uuid);
        },

        async resendSetup(uuid) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/organizations/${uuid}/resend-setup`);
            return data;
        },

        async updateSettings(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.patch(`/admin/organizations/${uuid}/settings`, payload);
            return data;
        },

        async fetchStats(uuid) {
            const { data } = await http.get(`/admin/organizations/${uuid}/stats`);
            return data;
        },

        async fetchTeam(uuid) {
            const { data } = await http.get(`/admin/organizations/${uuid}/team`);
            return data;
        },

        async inviteTeam(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/organizations/${uuid}/team`, payload);
            return data;
        },

        async removeTeam(uuid, userUuid) {
            await ensureCsrf();
            await http.delete(`/admin/organizations/${uuid}/team/${userUuid}`);
        },
    },
});
