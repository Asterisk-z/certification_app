import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';

export const useTemplatesStore = defineStore('templates', {
    state: () => ({
        items: [],
        meta: null,
        current: null,
        loading: false,
        filters: { q: '', status: '', page: 1 },
    }),

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get('/admin/templates', { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`/admin/templates/${uuid}`);
            this.current = data;
            return data;
        },

        async create(formData) {
            await ensureCsrf();
            const { data } = await http.post('/admin/templates', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        async update(uuid, formData) {
            await ensureCsrf();
            formData.append('_method', 'PUT');
            const { data } = await http.post(`/admin/templates/${uuid}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`/admin/templates/${uuid}`);
            this.items = this.items.filter((t) => t.uuid !== uuid);
        },

        async duplicate(uuid) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/templates/${uuid}/duplicate`);
            return data;
        },

        async saveLayout(uuid, blocks) {
            await ensureCsrf();
            const { data } = await http.put(`/admin/templates/${uuid}/layout`, { blocks });
            this.current = data;
            return data;
        },

        async addBlock(uuid, payload) {
            await ensureCsrf();
            const isForm = payload instanceof FormData;
            const { data } = await http.post(`/admin/templates/${uuid}/blocks`, payload, {
                headers: isForm ? { 'Content-Type': 'multipart/form-data' } : {},
            });
            return data;
        },

        async updateBlock(blockUuid, payload) {
            await ensureCsrf();
            if (payload instanceof FormData) {
                payload.append('_method', 'PUT');
                const { data } = await http.post(`/admin/blocks/${blockUuid}`, payload, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                return data;
            }
            const { data } = await http.put(`/admin/blocks/${blockUuid}`, payload);
            return data;
        },

        async deleteBlock(blockUuid) {
            await ensureCsrf();
            await http.delete(`/admin/blocks/${blockUuid}`);
        },
    },
});
