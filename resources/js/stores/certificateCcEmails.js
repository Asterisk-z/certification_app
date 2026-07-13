import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';
import { apiBase } from '@/api/area';

export const useCertificateCcEmailsStore = defineStore('certificateCcEmails', {
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
                const { data } = await http.get(`${apiBase()}/certificate-cc-emails`, { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            await ensureCsrf();
            const { data } = await http.post(`${apiBase()}/certificate-cc-emails`, payload);
            return data;
        },

        async update(uuid, payload) {
            await ensureCsrf();
            const { data } = await http.put(`${apiBase()}/certificate-cc-emails/${uuid}`, payload);
            return data;
        },

        async destroy(uuid) {
            await ensureCsrf();
            await http.delete(`${apiBase()}/certificate-cc-emails/${uuid}`);
            this.items = this.items.filter((e) => e.uuid !== uuid);
        },
    },
});
