<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '@/api/http';
import { useCertificatesStore } from '@/stores/certificates';
import { useUiStore } from '@/stores/ui';

const router = useRouter();
const store = useCertificatesStore();
const ui = useUiStore();

const templates = ref([]);
const recipients = ref([]);
const groups = ref([]);
const saving = ref(false);
const errors = ref({});
const selectedTemplate = ref(null);
const file = ref(null);
const fileInput = ref(null);

const form = reactive({
    template_uuid: '',
    recipient_uuid: '',
    group_uuid: '',
    title: '',
    certificate_number: '',
    completion_date: new Date().toISOString().slice(0, 10),
    issue_date: new Date().toISOString().slice(0, 10),
    data: {},
    send_now: false,
});

const hasTemplate = computed(() => Boolean(form.template_uuid));

const customFields = computed(() =>
    (selectedTemplate.value?.blocks || []).filter(
        (b) => b.is_dynamic && !['full_name', 'email', 'completion_date', 'issue_date', 'certificate_number', 'qr_code'].includes(b.slug)
    )
);

onMounted(async () => {
    const [templatesResponse, recipientsResponse, groupsResponse] = await Promise.all([
        http.get('/admin/templates', { params: { per_page: 100 } }),
        http.get('/admin/recipients', { params: { per_page: 200 } }),
        http.get('/admin/groups', { params: { per_page: 200 } }),
    ]);
    templates.value = templatesResponse.data.data;
    recipients.value = recipientsResponse.data.data;
    groups.value = groupsResponse.data.data;
});

async function onTemplateChange() {
    selectedTemplate.value = null;
    form.data = {};
    if (!form.template_uuid) return;
    const { data } = await http.get(`/admin/templates/${form.template_uuid}`);
    selectedTemplate.value = data;
    form.data = Object.fromEntries(customFields.value.map((b) => [b.slug, '']));
}

function onFileChange(event) {
    file.value = event.target.files[0] || null;
}

function clearFile() {
    file.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function buildPayload() {
    const fd = new FormData();
    if (form.template_uuid) fd.append('template_uuid', form.template_uuid);
    fd.append('recipient_uuid', form.recipient_uuid);
    if (form.group_uuid) fd.append('group_uuid', form.group_uuid);
    if (!hasTemplate.value && form.title) fd.append('title', form.title);
    if (form.certificate_number) fd.append('certificate_number', form.certificate_number);
    if (form.completion_date) fd.append('completion_date', form.completion_date);
    fd.append('issue_date', form.issue_date);
    fd.append('send_now', hasTemplate.value && form.send_now ? '1' : '0');
    Object.entries(form.data || {}).forEach(([slug, value]) => fd.append(`data[${slug}]`, value ?? ''));
    if (file.value) fd.append('file', file.value);
    return fd;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const certificate = await store.createManual(buildPayload());
        ui.success(`Credential ${certificate.certificate_number} created.`);
        router.push({ name: 'admin.certificates.detail', params: { uuid: certificate.uuid } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            ui.error(e.response?.data?.message || 'Could not create the credential.');
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Create credential manually</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Issue a single credential from a template, or register an existing certificate by
            choosing “No template” and uploading the finished file.
        </p>

        <form class="mt-6 space-y-5 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Template</label>
                    <select
                        v-model="form.template_uuid"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        @change="onTemplateChange"
                    >
                        <option value="">No template — register an uploaded certificate</option>
                        <option v-for="t in templates" :key="t.uuid" :value="t.uuid">{{ t.name }} ({{ t.code }})</option>
                    </select>
                    <p v-if="errors.template_uuid" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.template_uuid[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Recipient</label>
                    <select
                        v-model="form.recipient_uuid" required
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="" disabled>Select…</option>
                        <option v-for="r in recipients" :key="r.uuid" :value="r.uuid">{{ r.full_name }} — {{ r.email }}</option>
                    </select>
                    <p v-if="errors.recipient_uuid" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.recipient_uuid[0] }}</p>
                </div>

                <div v-if="!hasTemplate" class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Certificate title</label>
                    <input
                        v-model="form.title" type="text" required placeholder="e.g. Authorized Gas Tester"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Names the credential since there is no template.</p>
                    <p v-if="errors.title" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.title[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Group (optional)</label>
                    <select
                        v-model="form.group_uuid"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="">No group</option>
                        <option v-for="g in groups" :key="g.uuid" :value="g.uuid">{{ g.name }}</option>
                    </select>
                    <p v-if="errors.group_uuid" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.group_uuid[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Credential number (optional)</label>
                    <input
                        v-model="form.certificate_number" type="text" placeholder="Auto-generated when empty"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <p v-if="errors.certificate_number" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.certificate_number[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Completion date</label>
                    <input v-model="form.completion_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Issue date</label>
                    <input v-model="form.issue_date" type="date" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                </div>
            </div>

            <div v-if="customFields.length" class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Dynamic fields</h2>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="field in customFields" :key="field.slug">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ field.name }}</label>
                        <input v-model="form.data[field.slug]" type="text" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                </div>
            </div>

            <!-- Upload the finished certificate file -->
            <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Certificate file <span class="font-normal text-slate-500 dark:text-slate-400">({{ hasTemplate ? 'optional' : 'recommended' }})</span>
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Attach a finished PDF. When present it is used for downloads and the email attachment instead of a rendered file —
                    required if you want a downloadable credential without a template.
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <input
                        ref="fileInput" type="file" accept="application/pdf"
                        class="block w-full text-sm text-slate-600 dark:text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 dark:file:bg-brand-900/30 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 dark:file:text-brand-300"
                        @change="onFileChange"
                    />
                    <button v-if="file" type="button" class="shrink-0 text-xs font-medium text-slate-500 hover:text-rose-600 dark:text-slate-400" @click="clearFile">
                        Remove
                    </button>
                </div>
                <p v-if="errors.file" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.file[0] }}</p>
            </div>

            <label v-if="hasTemplate" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.send_now" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500" />
                Email it to the recipient immediately
            </label>
            <p v-else class="rounded-lg bg-slate-50 dark:bg-slate-800/60 px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                Without a template the credential is registered as already issued (verifiable immediately) — no email is sent.
            </p>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row sm:justify-end">
                <router-link :to="{ name: 'admin.certificates' }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    Cancel
                </router-link>
                <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ saving ? 'Creating…' : 'Create credential' }}
                </button>
            </div>
        </form>
    </div>
</template>
