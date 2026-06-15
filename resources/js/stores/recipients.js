import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';
import { apiBase } from '@/api/area';

export const useRecipientsStore = defineStore('recipients', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', group: '', organization: '', page: 1 },
        selected: [],
    }),

    getters: {
        allSelected: (state) => state.items.length > 0 && state.selected.length === state.items.length,
    },

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get(`${apiBase()}/recipients`, { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
                this.selected = [];
            } finally {
                this.loading = false;
            }
        },

        toggleSelectAll() {
            this.selected = this.allSelected ? [] : this.items.map((r) => r.uuid);
        },

        async bulkAction(action) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/recipients/bulk-action`, {
                action,
                uuids: this.selected,
            });
            return data;
        },

        async create(payload) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/recipients`, payload);
            return data;
        },

        async update(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.put(`${apiBase()}/recipients/${uuid}`, payload);
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`${apiBase()}/recipients/${uuid}`);
            this.items = this.items.filter((r) => r.uuid !== uuid);
        },

        async invite(uuid) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/recipients/${uuid}/invite`);
            return data;
        },
    },
});

export const useGroupsStore = defineStore('groups', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', organization: '', page: 1 },
    }),

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get(`${apiBase()}/groups`, { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async fetchAll() {
            const { data } = await http.get(`${apiBase()}/groups`, { params: { per_page: 200 } });
            return data.data;
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`${apiBase()}/groups/${uuid}`);
            return data;
        },

        async create(payload) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/groups`, payload);
            return data;
        },

        async update(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.put(`${apiBase()}/groups/${uuid}`, payload);
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`${apiBase()}/groups/${uuid}`);
            this.items = this.items.filter((g) => g.uuid !== uuid);
        },

        async addRecipients(uuid, recipientUuids) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/groups/${uuid}/recipients`, { recipient_uuids: recipientUuids });
            return data;
        },

        async removeRecipient(uuid, recipientUuid) {
            await ensureCsrf();
            const { data } = await http.delete(`${apiBase()}/groups/${uuid}/recipients/${recipientUuid}`);
            return data;
        },
    },
});
