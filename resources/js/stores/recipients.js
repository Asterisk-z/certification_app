import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';

export const useRecipientsStore = defineStore('recipients', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', group: '', page: 1 },
    }),

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get('/admin/recipients', { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            await ensureCsrf();
            const { data } = await http.post('/admin/recipients', payload);
            return data;
        },

        async update(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.put(`/admin/recipients/${uuid}`, payload);
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`/admin/recipients/${uuid}`);
            this.items = this.items.filter((r) => r.uuid !== uuid);
        },

        async invite(uuid) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/recipients/${uuid}/invite`);
            return data;
        },
    },
});

export const useGroupsStore = defineStore('groups', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', page: 1 },
    }),

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get('/admin/groups', { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async fetchAll() {
            const { data } = await http.get('/admin/groups', { params: { per_page: 200 } });
            return data.data;
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`/admin/groups/${uuid}`);
            return data;
        },

        async create(payload) {
            await ensureCsrf();
            const { data } = await http.post('/admin/groups', payload);
            return data;
        },

        async update(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.put(`/admin/groups/${uuid}`, payload);
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`/admin/groups/${uuid}`);
            this.items = this.items.filter((g) => g.uuid !== uuid);
        },

        async addRecipients(uuid, recipientUuids) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/groups/${uuid}/recipients`, { recipient_uuids: recipientUuids });
            return data;
        },

        async removeRecipient(uuid, recipientUuid) {
            await ensureCsrf();
            const { data } = await http.delete(`/admin/groups/${uuid}/recipients/${recipientUuid}`);
            return data;
        },
    },
});
