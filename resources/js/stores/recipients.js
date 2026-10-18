import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/api/http';
import { apiBase } from '@/api/area';

export const useRecipientsStore = defineStore('recipients', {
    state: () => ({
        items: [],
        meta: null,
        loading: false,
        filters: {
            q: '',
            group: '',
            organization: '',
            // Per-column filters + sort (see RecipientController::index).
            name: '',
            email: '',
            organization_name: '',
            certificates: '',
            portal: '',
            sort: 'created',
            dir: 'desc',
            page: 1,
        },
        selected: [],
        // True once the admin widens the page selection to every recipient
        // matching the current filters (across all pages).
        selectAll: false,
    }),

    getters: {
        allSelected: (state) => state.items.length > 0 && state.selected.length === state.items.length,
        // How many recipients a bulk action will touch.
        selectionCount: (state) => (state.selectAll ? state.meta?.total ?? 0 : state.selected.length),
        // Only the filter keys the API's bulk "all" mode understands (no paging/sorting).
        activeFilters: (state) => {
            const keys = ['q', 'name', 'email', 'organization_name', 'organization', 'group', 'certificates', 'portal'];
            return Object.fromEntries(keys.filter((k) => state.filters[k]).map((k) => [k, state.filters[k]]));
        },
    },

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await http.get(`${apiBase()}/recipients`, { params: this.filters });
                this.items = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
                this.clearSelection();
            } finally {
                this.loading = false;
            }
        },

        toggleSelectAll() {
            this.selected = this.allSelected ? [] : this.items.map((r) => r.uuid);
            this.selectAll = false;
        },

        selectAllMatching() {
            this.selected = this.items.map((r) => r.uuid);
            this.selectAll = true;
        },

        clearSelection() {
            this.selected = [];
            this.selectAll = false;
        },

        async bulkAction(action) {
            await ensureCsrf();
            const body = this.selectAll
                ? { action, all: true, filters: this.activeFilters }
                : { action, uuids: this.selected };
            const { data } = await http.post(`${apiBase()}/recipients/bulk-action`, body);
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
