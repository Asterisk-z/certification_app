<script setup>
import { onMounted, ref } from 'vue';
import http from '@/api/http';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import AppPagination from '@/components/ui/AppPagination.vue';

const entries = ref([]);
const meta = ref(null);
const loading = ref(true);
const q = ref('');
const page = ref(1);

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/logs/activity', { params: { q: q.value, page: page.value } });
        entries.value = data.data;
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function subjectLabel(entry) {
    if (!entry.subject_type) return '';
    return entry.subject_type.split('\\').pop();
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Activity logs</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">An audit trail of everything that happens on the platform.</p>

        <div class="mt-6">
            <DebouncedSearchInput v-model="q" placeholder="Search actions…" @search="page = 1; load();" />
        </div>

        <div class="mt-6 space-y-2">
            <p v-if="loading" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</p>
            <p v-else-if="!entries.length" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No activity recorded.</p>
            <div
                v-for="entry in entries" v-else :key="entry.id"
                class="rounded-xl bg-white dark:bg-slate-900 px-4 py-3 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-900 dark:text-slate-100">
                        <span class="font-semibold">{{ entry.causer?.name || 'System' }}</span>
                        <span class="text-slate-600 dark:text-slate-400"> — {{ entry.description.replaceAll('_', ' ') }}</span>
                        <span v-if="subjectLabel(entry)" class="ml-1 rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ subjectLabel(entry) }} #{{ entry.subject_id }}
                        </span>
                    </p>
                    <span class="shrink-0 text-xs text-slate-400">{{ entry.created_at?.slice(0, 19).replace('T', ' ') }}</span>
                </div>
                <pre
                    v-if="entry.properties && Object.keys(entry.properties).length"
                    class="mt-2 overflow-x-auto rounded-lg bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-600 dark:text-slate-400"
                >{{ JSON.stringify(entry.properties, null, 1) }}</pre>
            </div>
        </div>

        <AppPagination :meta="meta" @change="(p) => { page = p; load(); }" />
    </div>
</template>
