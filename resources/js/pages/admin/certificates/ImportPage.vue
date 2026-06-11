<script setup>
import { onMounted, ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { useGroupsStore } from '@/stores/recipients';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const groupsStore = useGroupsStore();

const templates = ref([]);
const groups = ref([]);
const templateUuid = ref('');
const groupUuid = ref('');
const file = ref(null);
const uploading = ref(false);
const result = ref(null);
const errorMessage = ref('');

onMounted(async () => {
    const { data } = await http.get('/admin/templates', { params: { per_page: 100 } });
    templates.value = data.data;
    groups.value = await groupsStore.fetchAll();
});

function downloadFormat() {
    if (!templateUuid.value) return;
    window.open(`/api/admin/templates/${templateUuid.value}/import-format`, '_blank');
}

async function submit() {
    if (!templateUuid.value || !file.value) return;
    uploading.value = true;
    result.value = null;
    errorMessage.value = '';
    try {
        await ensureCsrf();
        const fd = new FormData();
        fd.append('file', file.value);
        if (groupUuid.value) fd.append('group_uuid', groupUuid.value);
        const { data } = await http.post(`/admin/templates/${templateUuid.value}/import`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        result.value = data;
        ui.success(data.message);
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Import failed.';
        ui.error(errorMessage.value);
    } finally {
        uploading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Import recipients</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Upload an Excel file to create recipients and pending certificates for a template.
        </p>

        <div class="mt-6 space-y-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">1 · Choose template</label>
                <select
                    v-model="templateUuid"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                >
                    <option value="" disabled>Select a template…</option>
                    <option v-for="t in templates" :key="t.uuid" :value="t.uuid">
                        {{ t.name }} ({{ t.code }})
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">2 · Download the format</label>
                <p class="text-xs text-slate-400">
                    The file contains the default columns (full name, email, completion date, issue date) plus this
                    template's dynamic fields.
                </p>
                <button
                    class="mt-2 rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 disabled:opacity-40"
                    :disabled="!templateUuid"
                    @click="downloadFormat"
                >
                    ⬇ Download Excel format
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">3 · Add to group (optional)</label>
                <select
                    v-model="groupUuid"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                >
                    <option value="">No group</option>
                    <option v-for="g in groups" :key="g.uuid" :value="g.uuid">{{ g.name }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">4 · Upload the completed file</label>
                <input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="mt-2 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100"
                    @change="file = $event.target.files[0]"
                />
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <button
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 sm:w-auto"
                    :disabled="!templateUuid || !file || uploading"
                    @click="submit"
                >
                    {{ uploading ? 'Importing…' : 'Import' }}
                </button>
            </div>

            <div v-if="errorMessage" class="rounded-lg bg-rose-50 dark:bg-rose-950/50 p-4 text-sm text-rose-800 dark:text-rose-300">{{ errorMessage }}</div>

            <div v-if="result" class="space-y-3">
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-4 text-sm text-emerald-800 dark:text-emerald-300">
                    {{ result.created }} certificate(s) created as pending. Go to
                    <router-link :to="{ name: 'admin.certificates' }" class="font-semibold underline">Certificates</router-link>
                    to review and send them.
                </div>
                <div v-if="result.failures?.length" class="rounded-lg bg-amber-50 dark:bg-amber-950/50 p-4 text-sm text-amber-900 dark:text-amber-300">
                    <p class="font-semibold">{{ result.failures.length }} row(s) skipped:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li v-for="failure in result.failures" :key="failure.row">
                            Row {{ failure.row }}: {{ failure.errors.join('; ') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
