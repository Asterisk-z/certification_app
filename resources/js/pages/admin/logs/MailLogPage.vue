<script setup>
import { onMounted, ref } from 'vue';
import http from '@/api/http';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import AppPagination from '@/components/ui/AppPagination.vue';

const logs = ref([]);
const meta = ref(null);
const loading = ref(true);
const q = ref('');
const status = ref('');
const page = ref(1);

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/logs/mail', { params: { q: q.value, status: status.value, page: page.value } });
        logs.value = data.data;
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

onMounted(load);

const statusColors = {
    sent: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300',
    queued: 'bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-300 dark:text-sky-300',
    failed: 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300',
};

function mailType(value) {
    return value ? value.split('\\').pop().replace(/Mail$/, '') : '—';
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Mail logs</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every email the platform has queued, sent or failed to send.</p>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <DebouncedSearchInput v-model="q" placeholder="Search email or subject…" @search="page = 1; load();" />
            <select
                v-model="status"
                class="rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                @change="page = 1; load();"
            >
                <option value="">All statuses</option>
                <option value="queued">Queued</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <!-- Desktop -->
        <div class="mt-6 hidden overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 md:block">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">To</th>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading"><td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Loading…</td></tr>
                    <tr v-else-if="!logs.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No mail logs.</td></tr>
                    <tr v-for="log in logs" v-else :key="log.uuid" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ log.recipient_email }}</td>
                        <td class="max-w-xs truncate px-4 py-3 text-slate-600 dark:text-slate-400">{{ log.subject }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ mailType(log.mailable_type) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusColors[log.status]">
                                {{ log.status }}
                            </span>
                            <p v-if="log.error" class="mt-1 max-w-xs truncate text-xs text-rose-600 dark:text-rose-400" :title="log.error">{{ log.error }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ log.created_at?.slice(0, 16).replace('T', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile -->
        <div class="mt-6 space-y-3 md:hidden">
            <p v-if="loading" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</p>
            <p v-else-if="!logs.length" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No mail logs.</p>
            <div v-for="log in logs" v-else :key="log.uuid" class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ log.recipient_email }}</p>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusColors[log.status]">
                        {{ log.status }}
                    </span>
                </div>
                <p class="mt-1 truncate text-sm text-slate-600 dark:text-slate-400">{{ log.subject }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ mailType(log.mailable_type) }} · {{ log.created_at?.slice(0, 16).replace('T', ' ') }}</p>
                <p v-if="log.error" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ log.error }}</p>
            </div>
        </div>

        <AppPagination :meta="meta" @change="(p) => { page = p; load(); }" />
    </div>
</template>
