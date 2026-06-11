<script setup>
import { onMounted, ref } from 'vue';
import http from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';

const auth = useAuthStore();
const certificates = ref([]);
const meta = ref(null);
const loading = ref(true);
const q = ref('');
const page = ref(1);

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/me/certificates', { params: { q: q.value, page: page.value } });
        certificates.value = data.data;
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function download(certificate, format = 'pdf') {
    window.open(`/api/me/certificates/${certificate.uuid}/download?format=${format}`, '_blank');
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Welcome, {{ auth.user?.name }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">All credentials issued to you, in one place.</p>

        <div class="mt-6">
            <DebouncedSearchInput v-model="q" placeholder="Search your credentials…" @search="page = 1; load();" />
        </div>

        <div v-if="loading" class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>

        <div v-else-if="!certificates.length" class="mt-10 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
            <p class="font-medium text-slate-900 dark:text-slate-100">No credentials yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Credentials sent to you will appear here.</p>
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="certificate in certificates"
                :key="certificate.uuid"
                class="overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="relative aspect-[1123/794] bg-slate-100 dark:bg-slate-800">
                    <img
                        v-if="certificate.template?.background_image"
                        :src="`/storage/${certificate.template.background_image}`"
                        class="h-full w-full object-cover" alt=""
                    />
                    <StatusBadge :status="certificate.status" class="absolute right-3 top-3" />
                </div>
                <div class="p-4">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ certificate.template?.name || certificate.title || 'Credential' }}</h2>
                    <p class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ certificate.certificate_number }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Issued {{ certificate.issue_date?.slice(0, 10) }}
                        <template v-if="certificate.expiry_date"> · expires {{ certificate.expiry_date.slice(0, 10) }}</template>
                    </p>
                    <div class="mt-4 flex gap-2 text-sm">
                        <router-link
                            :to="{ name: 'portal.certificate', params: { uuid: certificate.uuid } }"
                            class="rounded-lg bg-brand-600 px-3 py-1.5 font-medium text-white hover:bg-brand-700"
                        >
                            View
                        </router-link>
                        <button
                            class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                            @click="download(certificate)"
                        >
                            PDF
                        </button>
                        <button
                            class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                            @click="download(certificate, 'png')"
                        >
                            PNG
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <AppPagination :meta="meta" @change="(p) => { page = p; load(); }" />
    </div>
</template>
