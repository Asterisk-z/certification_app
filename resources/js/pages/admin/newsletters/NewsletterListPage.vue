<script setup>
import { onMounted, ref } from 'vue';
import http from '@/api/http';
import AppPagination from '@/components/ui/AppPagination.vue';

const newsletters = ref([]);
const meta = ref(null);
const loading = ref(true);
const page = ref(1);

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/newsletters', { params: { page: page.value } });
        newsletters.value = data.data;
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

onMounted(load);

const audienceLabels = { all: 'Everyone', recipients: 'Certified users', admins: 'Admins' };
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Newsletters</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Updates sent to your users.</p>
            </div>
            <router-link
                :to="{ name: 'admin.newsletters.compose' }"
                class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700"
            >
                + Compose
            </router-link>
        </div>

        <div v-if="loading" class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        <div v-else-if="!newsletters.length" class="mt-10 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
            <p class="font-medium text-slate-900 dark:text-slate-100">No newsletters yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Compose your first update to reach your users.</p>
        </div>

        <div v-else class="mt-6 space-y-3">
            <div
                v-for="newsletter in newsletters"
                :key="newsletter.uuid"
                class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ newsletter.subject }}</h2>
                    <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">
                        {{ newsletter.sent_at ? newsletter.sent_at.slice(0, 16).replace('T', ' ') : 'Sending…' }}
                    </span>
                </div>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ newsletter.body }}</p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    To {{ audienceLabels[newsletter.audience] || newsletter.audience }} ·
                    {{ newsletter.recipients_count }} recipient(s) · by {{ newsletter.sender?.name }}
                </p>
            </div>
        </div>

        <AppPagination :meta="meta" @change="(p) => { page = p; load(); }" />
    </div>
</template>
