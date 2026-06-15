import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';

export const useCertificatesStore = defineStore('certificates', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: { q: '', status: 'all', template: '', page: 1 },
        selected: [],
    }),

    getters: {
        allSelected: (state) => state.items.length > 0 && state.selected.length === state.items.length,
    },

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get('/admin/certificates', { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
                this.selected = [];
            } finally {
                this.loading = false;
            }
        },

        async fetchOne(uuid) {
            const { data } = await http.get(`/admin/certificates/${uuid}`);
            return data;
        },

        toggleSelectAll() {
            this.selected = this.allSelected ? [] : this.items.map((c) => c.uuid);
        },

        async action(uuid, action, payload = {}) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/certificates/${uuid}/${action}`, payload);
            return data;
        },

        async bulk(action) {
            await ensureCsrf();
            const { data } = await http.post('/admin/certificates/bulk', {
                action,
                uuids: this.selected,
            });
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`/admin/certificates/${uuid}`);
        },

        async sendTemplate(templateUuid, payload) {
            await ensureCsrf();
            const { data } = await http.post(`/admin/templates/${templateUuid}/send`, payload);
            return data;
        },

        async createManual(payload) {
            await ensureCsrf();
            const isForm = payload instanceof FormData;
            const { data } = await http.post('/admin/certificates/manual', payload, {
                headers: isForm ? { 'Content-Type': 'multipart/form-data' } : {},
            });
            return data;
        },

        async uploadFile(uuid, file) {
            await ensureCsrf();
            const fd = new FormData();
            fd.append('file', file);
            const { data } = await http.post(`/admin/certificates/${uuid}/upload`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data;
        },

        download(uuid, format = 'pdf') {
            window.open(`/api/admin/certificates/${uuid}/download?format=${format}`, '_blank');
        },
    },
});
