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
const saving = ref(false);
const errors = ref({});
const selectedTemplate = ref(null);

const form = reactive({
    template_uuid: '',
    recipient_uuid: '',
    certificate_number: '',
    completion_date: new Date().toISOString().slice(0, 10),
    issue_date: new Date().toISOString().slice(0, 10),
    data: {},
    send_now: false,
});

const customFields = computed(() =>
    (selectedTemplate.value?.blocks || []).filter(
        (b) => b.is_dynamic && !['full_name', 'email', 'completion_date', 'issue_date', 'certificate_number', 'qr_code'].includes(b.slug)
    )
);

onMounted(async () => {
    const [templatesResponse, recipientsResponse] = await Promise.all([
        http.get('/admin/templates', { params: { per_page: 100 } }),
        http.get('/admin/recipients', { params: { per_page: 200 } }),
    ]);
    templates.value = templatesResponse.data.data;
    recipients.value = recipientsResponse.data.data;
});

async function onTemplateChange() {
    selectedTemplate.value = null;
    form.data = {};
    if (!form.template_uuid) return;
    const { data } = await http.get(`/admin/templates/${form.template_uuid}`);
    selectedTemplate.value = data;
    form.data = Object.fromEntries(customFields.value.map((b) => [b.slug, '']));
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const certificate = await store.createManual({
            ...form,
            certificate_number: form.certificate_number || null,
        });
        ui.success(`Certificate ${certificate.certificate_number} created.`);
        router.push({ name: 'admin.certificates.detail', params: { uuid: certificate.uuid } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            ui.error(e.response?.data?.message || 'Could not create the certificate.');
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Create certificate manually</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Issue a single certificate, optionally with your own certificate number.
        </p>

        <form class="mt-6 space-y-5 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Template</label>
                    <select
                        v-model="form.template_uuid" required
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        @change="onTemplateChange"
                    >
                        <option value="" disabled>Select…</option>
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
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Certificate number (optional)</label>
                    <input
                        v-model="form.certificate_number" type="text" placeholder="Auto-generated when empty"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <p v-if="errors.certificate_number" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.certificate_number[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Completion date</label>
                    <input v-model="form.completion_date" type="date" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
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

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.send_now" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500" />
                Email it to the recipient immediately
            </label>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row sm:justify-end">
                <router-link :to="{ name: 'admin.certificates' }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    Cancel
                </router-link>
                <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ saving ? 'Creating…' : 'Create certificate' }}
                </button>
            </div>
        </form>
    </div>
</template>
