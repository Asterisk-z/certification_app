<script setup>
import { computed, onMounted, ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { useGroupsStore } from '@/stores/recipients';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const groupsStore = useGroupsStore();

const templates = ref([]);
const groups = ref([]);
const mode = ref('new'); // new | existing
const templateUuid = ref('');
const groupUuid = ref('');
const file = ref(null);
const uploading = ref(false);
const result = ref(null);
const errorMessage = ref('');
const zipFile = ref(null);
const zipUploading = ref(false);
const zipResult = ref(null);

const modes = [
    {
        key: 'new',
        title: 'New credentials',
        text: 'Create pending credentials with generated numbers, then send them by email.',
    },
    {
        key: 'existing',
        title: 'Existing (offline) credentials',
        text: 'Register credentials already issued outside the system — original numbers are kept, they become verifiable immediately, and no emails are sent.',
    },
];

const isExisting = computed(() => mode.value === 'existing');

onMounted(async () => {
    const { data } = await http.get('/admin/templates', { params: { per_page: 100 } });
    templates.value = data.data;
    groups.value = await groupsStore.fetchAll();
});

function downloadFormat() {
    if (isExisting.value) {
        const query = templateUuid.value ? `?template_uuid=${templateUuid.value}` : '';
        window.open(`/api/admin/certificates/import-existing-format${query}`, '_blank');
        return;
    }
    if (!templateUuid.value) return;
    window.open(`/api/admin/templates/${templateUuid.value}/import-format`, '_blank');
}

async function submit() {
    if (!file.value || (!templateUuid.value && !isExisting.value)) return;
    uploading.value = true;
    result.value = null;
    errorMessage.value = '';
    try {
        await ensureCsrf();
        const fd = new FormData();
        fd.append('file', file.value);
        if (groupUuid.value) fd.append('group_uuid', groupUuid.value);
        let data;
        if (isExisting.value) {
            if (templateUuid.value) fd.append('template_uuid', templateUuid.value);
            ({ data } = await http.post('/admin/certificates/import-existing', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            }));
        } else {
            ({ data } = await http.post(`/admin/templates/${templateUuid.value}/import`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            }));
        }
        result.value = data;
        ui.success(data.message);
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Import failed.';
        ui.error(errorMessage.value);
    } finally {
        uploading.value = false;
    }
}

async function submitZip() {
    if (!zipFile.value) return;
    zipUploading.value = true;
    zipResult.value = null;
    try {
        await ensureCsrf();
        const fd = new FormData();
        fd.append('file', zipFile.value);
        const { data } = await http.post('/admin/certificates/attach-zip', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        zipResult.value = data;
        ui.success(data.message);
    } catch (e) {
        ui.error(e.response?.data?.message || 'ZIP upload failed.');
    } finally {
        zipUploading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Import credentials</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Bring in recipients and credentials from an Excel file — new ones to send, or ones already issued offline.
        </p>

        <div class="mt-6 space-y-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">1 · What are you importing?</label>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button
                        v-for="option in modes"
                        :key="option.key"
                        type="button"
                        class="rounded-xl border p-4 text-left transition"
                        :class="mode === option.key
                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 ring-1 ring-brand-500'
                            : 'border-slate-200 dark:border-slate-700 hover:border-brand-300'"
                        @click="mode = option.key; result = null; errorMessage = '';"
                    >
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ option.title }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ option.text }}</p>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    2 · Choose template{{ isExisting ? ' (optional)' : '' }}
                </label>
                <p v-if="isExisting" class="text-xs text-slate-400">
                    Skip this when the credentials are finished documents that already contain the recipient's
                    details — use the certificate_title column to say what each one certifies.
                </p>
                <select
                    v-model="templateUuid"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                >
                    <option value="" :disabled="!isExisting">
                        {{ isExisting ? 'No template — certificates carry their own details' : 'Select a template…' }}
                    </option>
                    <option v-for="t in templates" :key="t.uuid" :value="t.uuid">
                        {{ t.name }} ({{ t.code }})
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">3 · Download the format</label>
                <p class="text-xs text-slate-400">
                    <template v-if="isExisting">
                        Columns: certificate_number (the original number), full name, email, completion date,
                        issue date, expiry date — plus
                        <template v-if="templateUuid">this template's dynamic fields.</template>
                        <template v-else>a certificate_title column describing the credential.</template>
                    </template>
                    <template v-else>
                        The file contains the default columns (full name, email, completion date, issue date) plus
                        this template's dynamic fields.
                    </template>
                </p>
                <button
                    class="mt-2 rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 disabled:opacity-40"
                    :disabled="!templateUuid && !isExisting"
                    @click="downloadFormat"
                >
                    ⬇ Download Excel format
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">4 · Add to group (optional)</label>
                <select
                    v-model="groupUuid"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                >
                    <option value="">No group</option>
                    <option v-for="g in groups" :key="g.uuid" :value="g.uuid">{{ g.name }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">5 · Upload the completed file</label>
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
                    :disabled="!file || uploading || (!templateUuid && !isExisting)"
                    @click="submit"
                >
                    {{ uploading ? 'Importing…' : isExisting ? 'Register existing certificates' : 'Import' }}
                </button>
            </div>

            <div v-if="errorMessage" class="rounded-lg bg-rose-50 dark:bg-rose-950/50 p-4 text-sm text-rose-800 dark:text-rose-300">{{ errorMessage }}</div>

            <div v-if="result" class="space-y-3">
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-4 text-sm text-emerald-800 dark:text-emerald-300">
                    <template v-if="isExisting">
                        {{ result.created }} existing credential(s) registered — they verify by their original numbers
                        right away and appear in
                        <router-link :to="{ name: 'admin.certificates' }" class="font-semibold underline">Credentials</router-link>.
                        No emails were sent.
                    </template>
                    <template v-else>
                        {{ result.created }} credential(s) created as pending. Go to
                        <router-link :to="{ name: 'admin.certificates' }" class="font-semibold underline">Credentials</router-link>
                        to review and send them.
                    </template>
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

        <!-- Bulk-attach scanned files -->
        <div v-if="isExisting" class="mt-6 space-y-4 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Attach scanned credentials (optional)</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Upload a ZIP of PDFs named after the credential numbers (e.g.
                    <code class="font-mono">HSE-2023-0042.pdf</code> matches HSE/2023/0042 — case and separators
                    don't matter). Each match becomes that credential's official document for viewing and download.
                </p>
            </div>
            <input
                type="file"
                accept=".zip"
                class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100"
                @change="zipFile = $event.target.files[0]"
            />
            <button
                class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="!zipFile || zipUploading"
                @click="submitZip"
            >
                {{ zipUploading ? 'Attaching…' : 'Attach files' }}
            </button>
            <div v-if="zipResult" class="space-y-2">
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-3 text-sm text-emerald-800 dark:text-emerald-300">
                    {{ zipResult.attached.length }} file(s) attached.
                </div>
                <div v-if="zipResult.unmatched?.length" class="rounded-lg bg-amber-50 dark:bg-amber-950/50 p-3 text-sm text-amber-900 dark:text-amber-300">
                    <p class="font-semibold">{{ zipResult.unmatched.length }} file(s) had no matching credential:</p>
                    <p class="mt-1 break-all text-xs">{{ zipResult.unmatched.join(', ') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
