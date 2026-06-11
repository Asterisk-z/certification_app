<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCertificatesStore } from '@/stores/certificates';
import { useUiStore } from '@/stores/ui';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import RenewDialog from '@/components/certificates/RenewDialog.vue';

const route = useRoute();
const router = useRouter();
const store = useCertificatesStore();
const ui = useUiStore();

const certificate = ref(null);
const confirm = ref(null);
const uploading = ref(false);
const renewOpen = ref(false);

async function confirmRenew(dates) {
    renewOpen.value = false;
    await run('renew', 'Renewed — the new certificate is queued.', dates);
}

async function load() {
    certificate.value = await store.fetchOne(route.params.uuid);
}

onMounted(load);

async function run(action, successMessage, payload = {}) {
    try {
        await store.action(certificate.value.uuid, action, payload);
        ui.success(successMessage);
        await load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Action failed.');
    } finally {
        confirm.value = null;
    }
}

function revoke() {
    confirm.value = {
        title: 'Revoke certificate?',
        message: 'The recipient will be notified by email and verification will report it as revoked.',
        confirmLabel: 'Revoke',
        run: () => run('revoke', 'Certificate revoked.'),
    };
}

function remove() {
    confirm.value = {
        title: 'Delete certificate?',
        message: 'It will move to the Deleted tab, where it can be restored.',
        confirmLabel: 'Delete',
        run: async () => {
            try {
                await store.destroy(certificate.value.uuid);
                ui.success('Certificate deleted.');
                router.push({ name: 'admin.certificates' });
            } catch (e) {
                ui.error(e.response?.data?.message || 'Action failed.');
            } finally {
                confirm.value = null;
            }
        },
    };
}

async function upload(event) {
    const file = event.target.files[0];
    if (!file) return;
    uploading.value = true;
    try {
        await store.uploadFile(certificate.value.uuid, file);
        ui.success('Manual certificate file attached. It now replaces the generated PDF.');
        await load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Upload failed.');
    } finally {
        uploading.value = false;
    }
}
</script>

<template>
    <div v-if="certificate" class="mx-auto max-w-4xl">
        <router-link :to="{ name: 'admin.certificates' }" class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
            ← Back to certificates
        </router-link>

        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-mono text-xl font-bold text-slate-900 dark:text-slate-100 sm:text-2xl">{{ certificate.certificate_number }}</h1>
                    <StatusBadge :status="certificate.deleted_at ? 'deleted' : certificate.status" />
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ certificate.template?.name }}</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <button class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700" @click="store.download(certificate.uuid)">
                    Download PDF
                </button>
                <button
                    v-if="!certificate.uploaded_file_path"
                    class="rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                    @click="store.download(certificate.uuid, 'png')"
                >
                    Download PNG
                </button>
                <button
                    v-if="['sent', 'failed', 'pending'].includes(certificate.status)"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    @click="run('resend', 'Certificate queued for sending.')"
                >
                    {{ certificate.status === 'sent' ? 'Resend' : 'Send' }}
                </button>
                <button
                    v-if="['sent', 'expired'].includes(certificate.status)"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    @click="renewOpen = true"
                >
                    Renew
                </button>
                <button
                    v-if="certificate.status === 'revoked'"
                    class="rounded-lg border border-emerald-300 dark:border-emerald-800 px-4 py-2 font-medium text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                    @click="run('unrevoke', 'Certificate restored.')"
                >
                    Un-revoke
                </button>
                <button
                    v-if="['pending', 'queued', 'sent'].includes(certificate.status)"
                    class="rounded-lg border border-rose-200 dark:border-rose-900 px-4 py-2 font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                    @click="revoke"
                >
                    Revoke
                </button>
                <button
                    v-if="certificate.deleted_at"
                    class="rounded-lg border border-emerald-300 dark:border-emerald-800 px-4 py-2 font-medium text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                    @click="run('restore', 'Certificate restored from deleted.')"
                >
                    Restore
                </button>
                <button v-else class="rounded-lg border border-rose-200 dark:border-rose-900 px-4 py-2 font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40" @click="remove">
                    Delete
                </button>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Recipient</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Name</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ certificate.recipient?.full_name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Email</dt><dd class="text-slate-700 dark:text-slate-300">{{ certificate.recipient?.email }}</dd></div>
                    <div v-if="certificate.group" class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Group</dt><dd class="text-slate-700 dark:text-slate-300">{{ certificate.group.name }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Dates & status</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Completed</dt><dd class="text-slate-700 dark:text-slate-300">{{ certificate.completion_date?.slice(0, 10) || '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Issued</dt><dd class="text-slate-700 dark:text-slate-300">{{ certificate.issue_date?.slice(0, 10) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Expires</dt><dd class="text-slate-700 dark:text-slate-300">{{ certificate.expiry_date?.slice(0, 10) || 'Never' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Sent at</dt><dd class="text-slate-700 dark:text-slate-300">{{ certificate.sent_at ? certificate.sent_at.slice(0, 16).replace('T', ' ') : '—' }}</dd></div>
                    <div v-if="certificate.revoked_at" class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Revoked at</dt><dd class="text-rose-600 dark:text-rose-400">{{ certificate.revoked_at.slice(0, 16).replace('T', ' ') }}</dd></div>
                    <div v-if="certificate.renewed_from" class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Renewed from</dt><dd class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ certificate.renewed_from.certificate_number }}</dd></div>
                    <div v-if="certificate.send_error" class="rounded-lg bg-rose-50 dark:bg-rose-950/50 p-3 text-xs text-rose-700 dark:text-rose-300">{{ certificate.send_error }}</div>
                </dl>
            </div>

            <div v-if="Object.keys(certificate.data || {}).length" class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Dynamic field data</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div v-for="(value, slug) in certificate.data" :key="slug" class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ slug }}</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ value || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Manual file</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Upload a PDF to replace the generated certificate (used for downloads and email attachments).
                </p>
                <p v-if="certificate.uploaded_file_path" class="mt-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 px-3 py-2 text-sm text-emerald-800 dark:text-emerald-300">
                    A manual file is attached.
                </p>
                <label class="mt-3 inline-block cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    {{ uploading ? 'Uploading…' : certificate.uploaded_file_path ? 'Replace file' : 'Upload PDF' }}
                    <input type="file" accept="application/pdf" class="hidden" :disabled="uploading" @change="upload" />
                </label>
            </div>
        </div>

        <ConfirmDialog
            :open="!!confirm"
            :title="confirm?.title"
            :message="confirm?.message"
            :confirm-label="confirm?.confirmLabel"
            @confirm="confirm.run()"
            @cancel="confirm = null"
        />

        <RenewDialog
            :open="renewOpen"
            :certificate="certificate"
            @close="renewOpen = false"
            @confirm="confirmRenew"
        />
    </div>
    <div v-else class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">Loading certificate…</div>
</template>
