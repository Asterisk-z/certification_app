import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';
import { apiBase } from '@/api/area';

export const useTemplatesStore = defineStore('templates', {
    state: () => ({
        items: [],
        meta: null,
        current: null,
        loading: false,
        filters: { q: '', status: '', organization: '', page: 1 },
    }),

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get(`${apiBase()}/templates`, { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`${apiBase()}/templates/${uuid}`);
            this.current = data;
            return data;
        },

        async create(formData) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/templates`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        async update(uuid, formData) {
            await ensureCsrf();
            formData.append('_method', 'PUT');
            const { data } = await http.post(`${apiBase()}/templates/${uuid}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`${apiBase()}/templates/${uuid}`);
            this.items = this.items.filter((t) => t.uuid !== uuid);
        },

        async duplicate(uuid) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/templates/${uuid}/duplicate`);
            return data;
        },

        async saveLayout(uuid, blocks) {
            await ensureCsrf();
            const { data } = await http.put(`${apiBase()}/templates/${uuid}/layout`, { blocks });
            this.current = data;
            return data;
        },

        async addBlock(uuid, payload) {
            await ensureCsrf();
            const isForm = payload instanceof FormData;
            const { data } = await http.post(`${apiBase()}/templates/${uuid}/blocks`, payload, {
                headers: isForm ? { 'Content-Type': 'multipart/form-data' } : {},
            });
            return data;
        },

        async updateBlock(blockUuid, payload) {
            await ensureCsrf();
            if (payload instanceof FormData) {
                payload.append('_method', 'PUT');
                const { data } = await http.post(`${apiBase()}/blocks/${blockUuid}`, payload, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                return data;
            }
            const { data } = await http.put(`${apiBase()}/blocks/${blockUuid}`, payload);
            return data;
        },

        async deleteBlock(blockUuid) {
            await ensureCsrf();
            await http.delete(`${apiBase()}/blocks/${blockUuid}`);
        },
    },
});
