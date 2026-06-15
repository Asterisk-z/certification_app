import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';
import { apiBase } from '@/api/area';

export const useCertificatesStore = defineStore('certificates', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', status: 'all', template: '', organization: '', page: 1 },
        selected: [],
    }),

    getters: {
        allSelected: (state) => state.items.length > 0 && state.selected.length === state.items.length,
    },

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get(`${apiBase()}/certificates`, { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
                this.selected = [];
            } finally {
                this.loading = false;
            }
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`${apiBase()}/certificates/${uuid}`);
            return data;
        },

        toggleSelectAll() {
            this.selected = this.allSelected ? [] : this.items.map((c) => c.uuid);
        },

        async action(uuid, action, payload = {}) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/certificates/${uuid}/${action}`, payload);
            return data;
        },

        async bulk(action) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/certificates/bulk`, {
                action,
                uuids: this.selected,
            });
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`${apiBase()}/certificates/${uuid}`);
        },

        async sendTemplate(templateUuid, payload) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/templates/${templateUuid}/send`, payload);
            return data;
        },

        async createManual(payload) {
            await ensureCsrf();
            const isForm = payload instanceof FormData;
            const { data } = await http.post(`${apiBase()}/certificates/manual`, payload, {
                headers: isForm ? { 'Content-Type': 'multipart/form-data' } : {},
            });
            return data;
        },

        async uploadFile(uuid, file) {
            await ensureCsrf();
            const fd = new FormData();
            fd.append('file', file);
            const { data } = await http.post(`${apiBase()}/certificates/${uuid}/upload`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        download(uuid, format = 'pdf') {
            window.open(`/api${apiBase()}/certificates/${uuid}/download?format=${format}`, '_blank');
        },
    },
});
