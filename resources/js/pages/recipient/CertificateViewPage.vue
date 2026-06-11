<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/api/http';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const route = useRoute();
const certificate = ref(null);

onMounted(async () => {
    const { data } = await http.get(`/me/certificates/${route.params.uuid}`);
    certificate.value = data;
});

function download(format = 'pdf') {
    window.open(`/api/me/certificates/${certificate.value.uuid}/download?format=${format}`, '_blank');
}
</script>

<template>
    <div v-if="certificate">
        <router-link :to="{ name: 'portal.dashboard' }" class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
            ← Back to my certificates
        </router-link>

        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 sm:text-2xl">{{ certificate.template?.name || certificate.title || 'Certificate' }}</h1>
                    <StatusBadge :status="certificate.status" />
                </div>
                <p class="mt-1 font-mono text-sm text-slate-500 dark:text-slate-400">{{ certificate.certificate_number }}</p>
            </div>
            <div class="flex gap-2">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="download()">
                    Download PDF
                </button>
                <button
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                    @click="download('png')"
                >
                    Download PNG
                </button>
                <a
                    :href="`/?number=${encodeURIComponent(certificate.certificate_number)}`"
                    target="_blank"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                >
                    Verify publicly
                </a>
            </div>
        </div>

        <!-- Live certificate preview -->
        <div class="mt-6 overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <iframe
                :src="`/c/${certificate.uuid}`"
                class="aspect-[1123/794] w-full"
                title="Certificate preview"
            />
        </div>

        <div class="mt-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-slate-100">Details</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500 dark:text-slate-400">Completed</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ certificate.completion_date?.slice(0, 10) || '—' }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Issued</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ certificate.issue_date?.slice(0, 10) }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Valid until</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ certificate.expiry_date?.slice(0, 10) || 'No expiry' }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Status</dt><dd class="font-medium capitalize text-slate-900 dark:text-slate-100">{{ certificate.status }}</dd></div>
            </dl>
        </div>
    </div>
    <div v-else class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
</template>
