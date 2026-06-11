<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '@/api/http';
import { useCertificatesStore } from '@/stores/certificates';
import { useGroupsStore } from '@/stores/recipients';
import { useUiStore } from '@/stores/ui';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';

const router = useRouter();
const certStore = useCertificatesStore();
const groupsStore = useGroupsStore();
const ui = useUiStore();

const step = ref(1);
const templates = ref([]);
const groups = ref([]);
const recipients = ref([]);
const recipientQuery = ref('');
const sending = ref(false);

const form = reactive({
    template: null,
    group_uuid: '',
    recipient_uuids: [],
    completion_date: new Date().toISOString().slice(0, 10),
    issue_date: new Date().toISOString().slice(0, 10),
    data: {},
});

const customFields = computed(() =>
    (form.template?.blocks || []).filter(
        (b) => b.is_dynamic && !['full_name', 'email', 'completion_date', 'issue_date', 'certificate_number', 'qr_code'].includes(b.slug)
    )
);

const targetSummary = computed(() => {
    const parts = [];
    if (form.group_uuid) {
        const group = groups.value.find((g) => g.uuid === form.group_uuid);
        if (group) parts.push(`group “${group.name}” (${group.recipients_count} member(s))`);
    }
    if (form.recipient_uuids.length) parts.push(`${form.recipient_uuids.length} individual recipient(s)`);
    return parts.join(' + ') || 'no one yet';
});

onMounted(async () => {
    const { data } = await http.get('/admin/templates', { params: { per_page: 100, status: 'ready' } });
    templates.value = data.data;
    groups.value = await groupsStore.fetchAll();
    loadRecipients();
});

async function loadRecipients() {
    const { data } = await http.get('/admin/recipients', { params: { q: recipientQuery.value, per_page: 20 } });
    recipients.value = data.data;
}

function pickTemplate(template) {
    form.template = null;
    // Need block info for custom dynamic fields.
    http.get(`/admin/templates/${template.uuid}`).then(({ data }) => {
        form.template = data;
        form.data = Object.fromEntries(
            (data.blocks || [])
                .filter((b) => b.is_dynamic && !['full_name', 'email', 'completion_date', 'issue_date', 'certificate_number', 'qr_code'].includes(b.slug))
                .map((b) => [b.slug, ''])
        );
        step.value = 2;
    });
}

async function send() {
    sending.value = true;
    try {
        const payload = {
            completion_date: form.completion_date,
            issue_date: form.issue_date,
            data: form.data,
        };
        if (form.group_uuid) payload.group_uuid = form.group_uuid;
        if (form.recipient_uuids.length) payload.recipient_uuids = form.recipient_uuids;

        const result = await certStore.sendTemplate(form.template.uuid, payload);
        ui.success(result.message);
        router.push({ name: 'admin.certificates' });
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not queue the certificates.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Send certificates</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Choose a template, pick a group or individual recipients, set the dates, and queue the emails.
        </p>

        <!-- Steps -->
        <ol class="mt-6 flex gap-2 text-xs font-medium sm:text-sm">
            <li
                v-for="(label, i) in ['Template', 'Recipients', 'Details & send']"
                :key="label"
                class="flex items-center gap-2 rounded-full px-3 py-1.5"
                :class="step >= i + 1 ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
            >
                {{ i + 1 }}. {{ label }}
            </li>
        </ol>

        <!-- Step 1: template -->
        <div v-if="step === 1" class="mt-6">
            <p v-if="!templates.length" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                No templates are marked <strong>ready</strong>. Open a template's designer and save the layout first.
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="template in templates"
                    :key="template.uuid"
                    class="overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 transition hover:ring-2 hover:ring-brand-500"
                    @click="pickTemplate(template)"
                >
                    <div class="aspect-[1123/794] bg-slate-100 dark:bg-slate-800">
                        <img
                            v-if="template.background_image"
                            :src="`/storage/${template.background_image}`"
                            class="h-full w-full object-cover" alt=""
                        />
                    </div>
                    <div class="p-3">
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ template.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ template.code }}</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Step 2: recipients -->
        <div v-else-if="step === 2" class="mt-6 space-y-6">
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Send to a group</h2>
                <select
                    v-model="form.group_uuid"
                    class="mt-2 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:max-w-sm"
                >
                    <option value="">— No group —</option>
                    <option v-for="g in groups" :key="g.uuid" :value="g.uuid">
                        {{ g.name }} ({{ g.recipients_count }})
                    </option>
                </select>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">And / or individual recipients</h2>
                <div class="mt-2">
                    <DebouncedSearchInput v-model="recipientQuery" placeholder="Search recipients…" @search="loadRecipients" />
                </div>
                <div class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                    <label
                        v-for="recipient in recipients"
                        :key="recipient.uuid"
                        class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    >
                        <input
                            v-model="form.recipient_uuids" type="checkbox" :value="recipient.uuid"
                            class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ recipient.full_name }}</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ recipient.email }}</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex justify-between">
                <button class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="step = 1">
                    ← Back
                </button>
                <button
                    class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    :disabled="!form.group_uuid && !form.recipient_uuids.length"
                    @click="step = 3"
                >
                    Continue →
                </button>
            </div>
        </div>

        <!-- Step 3: details + send -->
        <div v-else class="mt-6 space-y-6">
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Dates</h2>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Completion date</label>
                        <input v-model="form.completion_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Issue date</label>
                        <input v-model="form.issue_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        <p v-if="form.template?.duration" class="mt-1 text-xs text-slate-400">
                            Expiry is computed automatically: {{ form.template.duration }} {{ form.template.duration_type }}(s) after issue.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="customFields.length" class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Shared dynamic fields</h2>
                <p class="mt-1 text-xs text-slate-400">
                    These values apply to everyone in this batch. For per-recipient values, use the Excel import instead.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="field in customFields" :key="field.slug">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ field.name }}</label>
                        <input
                            v-model="form.data[field.slug]" type="text"
                            class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-brand-50 dark:bg-brand-900/30 p-6 text-sm text-brand-900 dark:text-brand-200 ring-1 ring-brand-100 dark:ring-brand-900">
                Sending <strong>{{ form.template?.name }}</strong> to {{ targetSummary }}.
                Each recipient gets a personalised PDF and a verification link by email.
            </div>

            <div class="flex justify-between">
                <button class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="step = 2">
                    ← Back
                </button>
                <button
                    class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    :disabled="sending"
                    @click="send"
                >
                    {{ sending ? 'Queuing…' : 'Queue & send' }}
                </button>
            </div>
        </div>
    </div>
</template>
