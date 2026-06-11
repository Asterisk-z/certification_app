<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '@/api/http';
import { useCertificatesStore } from '@/stores/certificates';
import { useUiStore } from '@/stores/ui';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import ActionDropdown from '@/components/ui/ActionDropdown.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import RenewDialog from '@/components/certificates/RenewDialog.vue';

const store = useCertificatesStore();
const ui = useUiStore();
const router = useRouter();

const templates = ref([]);
const confirm = ref(null); // { title, message, confirmLabel, run }
const renewing = ref(null); // certificate being renewed (opens the dialog)

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'queued', label: 'Queued' },
    { key: 'sent', label: 'Sent' },
    { key: 'failed', label: 'Failed' },
    { key: 'revoked', label: 'Revoked' },
    { key: 'expired', label: 'Expired' },
    { key: 'renewed', label: 'Renewed' },
    { key: 'cancelled', label: 'Cancelled' },
    { key: 'deleted', label: 'Deleted' },
];

const showingDeleted = computed(() => store.filters.status === 'deleted');

let eventSource = null;

onMounted(async () => {
    store.fetch();

    // Live updates: the server emits an event whenever any certificate
    // changes (queue worker, imports, other admins). Skip the refresh while
    // rows are selected or a dialog is open so in-progress work isn't lost.
    eventSource = new EventSource('/api/admin/certificates/stream');
    eventSource.addEventListener('certificates', () => {
        if (!store.selected.length && !confirm.value && !renewing.value && !store.loading) {
            store.fetch();
        }
    });

    const { data } = await http.get('/admin/templates', { params: { per_page: 100 } });
    templates.value = data.data;
});

onBeforeUnmount(() => eventSource?.close());

function setTab(key) {
    store.filters.status = key;
    store.filters.page = 1;
    store.fetch();
}

function search() {
    store.filters.page = 1;
    store.fetch();
}

function actionsFor(certificate) {
    if (showingDeleted.value) {
        return [{ key: 'restore', label: 'Restore' }];
    }

    const actions = [
        { key: 'view', label: 'View details' },
        { key: 'download', label: 'Download PDF' },
        ...(certificate.uploaded_file_path ? [] : [{ key: 'download-png', label: 'Download PNG' }]),
    ];
    const status = certificate.status;

    if (['sent', 'failed', 'pending'].includes(status)) actions.push({ key: 'resend', label: status === 'sent' ? 'Resend' : 'Send' });
    if (['sent', 'expired'].includes(status)) actions.push({ key: 'renew', label: 'Renew' });
    if (['pending', 'queued', 'sent'].includes(status)) actions.push({ key: 'revoke', label: 'Revoke', danger: true });
    if (status === 'revoked') actions.push({ key: 'unrevoke', label: 'Restore (un-revoke)' });
    actions.push({ key: 'delete', label: 'Delete', danger: true });

    return actions;
}

async function handle(certificate, action) {
    if (action === 'view') {
        router.push({ name: 'admin.certificates.detail', params: { uuid: certificate.uuid } });
        return;
    }
    if (action === 'download') {
        store.download(certificate.uuid);
        return;
    }
    if (action === 'download-png') {
        store.download(certificate.uuid, 'png');
        return;
    }
    if (action === 'renew') {
        renewing.value = certificate;
        return;
    }
    if (action === 'revoke') {
        confirm.value = {
            title: 'Revoke certificate?',
            message: `${certificate.certificate_number} will be marked invalid and the recipient notified by email.`,
            confirmLabel: 'Revoke',
            run: () => runAction(certificate, 'revoke', 'Certificate revoked.'),
        };
        return;
    }
    if (action === 'delete') {
        confirm.value = {
            title: 'Delete certificate?',
            message: `${certificate.certificate_number} will move to the Deleted tab, where it can be restored.`,
            confirmLabel: 'Delete',
            run: async () => {
                try {
                    await store.destroy(certificate.uuid);
                    ui.success('Certificate deleted. You can restore it from the Deleted tab.');
                    store.fetch();
                } catch (e) {
                    ui.error(e.response?.data?.message || 'Action failed.');
                } finally {
                    confirm.value = null;
                }
            },
        };
        return;
    }

    const messages = {
        resend: 'Certificate queued for sending.',
        renew: 'Certificate renewed — the new one is queued for sending.',
        unrevoke: 'Certificate restored.',
        restore: 'Certificate restored from deleted.',
    };
    await runAction(certificate, action, messages[action] || 'Done.');
}

async function runAction(certificate, action, successMessage, payload = {}) {
    try {
        await store.action(certificate.uuid, action, payload);
        ui.success(successMessage);
        store.fetch();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Action failed.');
    } finally {
        confirm.value = null;
    }
}

async function confirmRenew(dates) {
    const certificate = renewing.value;
    renewing.value = null;
    await runAction(certificate, 'renew', 'Certificate renewed — the new one is queued for sending.', dates);
}

function bulk(action) {
    const labels = {
        revoke: { title: 'Revoke selected?', message: `${store.selected.length} certificate(s) will be revoked and recipients emailed.`, confirmLabel: 'Revoke' },
        cancel: { title: 'Cancel selected?', message: `${store.selected.length} certificate(s) will be cancelled. This cannot be undone.`, confirmLabel: 'Cancel them' },
        delete: { title: 'Delete selected?', message: `${store.selected.length} certificate(s) will move to Deleted (restorable).`, confirmLabel: 'Delete' },
        restore: { title: 'Restore selected?', message: `${store.selected.length} certificate(s) will be restored.`, confirmLabel: 'Restore' },
        resend: { title: 'Resend selected?', message: `${store.selected.length} certificate(s) will be queued for sending.`, confirmLabel: 'Resend' },
    };

    confirm.value = {
        ...labels[action],
        run: async () => {
            try {
                const result = await store.bulk(action);
                ui.success(result.message);
                store.fetch();
            } catch (e) {
                ui.error(e.response?.data?.message || 'Bulk action failed.');
            } finally {
                confirm.value = null;
            }
        },
    };
}

function recipientName(certificate) {
    return certificate.recipient?.full_name || '—';
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Certificates</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Track, send, revoke and renew issued credentials.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <router-link :to="{ name: 'admin.certificates.import' }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    Import Excel
                </router-link>
                <router-link :to="{ name: 'admin.certificates.manual' }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    Manual create
                </router-link>
                <router-link :to="{ name: 'admin.certificates.send' }" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
                    Send certificates
                </router-link>
            </div>
        </div>

        <!-- Status tabs -->
        <div class="mt-6 flex gap-1 overflow-x-auto pb-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium"
                :class="store.filters.status === tab.key ? 'bg-brand-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                @click="setTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <DebouncedSearchInput v-model="store.filters.q" placeholder="Search number, recipient, template…" @search="search" />
            <select
                v-model="store.filters.template"
                class="rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                @change="search"
            >
                <option value="">All templates</option>
                <option v-for="t in templates" :key="t.uuid" :value="t.uuid">{{ t.name }}</option>
            </select>
        </div>

        <!-- Bulk action bar -->
        <div
            v-if="store.selected.length"
            class="sticky top-2 z-20 mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-lg"
        >
            <span class="font-medium">{{ store.selected.length }} selected</span>
            <span class="hidden text-slate-400 sm:inline">·</span>
            <template v-if="showingDeleted">
                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 font-medium hover:bg-emerald-500" @click="bulk('restore')">Restore</button>
            </template>
            <template v-else>
                <button class="rounded-lg bg-slate-700 px-3 py-1.5 font-medium hover:bg-slate-600" @click="bulk('resend')">Resend</button>
                <button class="rounded-lg bg-rose-600 px-3 py-1.5 font-medium hover:bg-rose-500" @click="bulk('revoke')">Revoke</button>
                <button class="rounded-lg bg-slate-700 px-3 py-1.5 font-medium hover:bg-slate-600" @click="bulk('cancel')">Cancel</button>
                <button class="rounded-lg bg-rose-700 px-3 py-1.5 font-medium hover:bg-rose-600" @click="bulk('delete')">Delete</button>
            </template>
            <button class="ml-auto text-slate-300 hover:text-white" @click="store.selected = []">Clear</button>
        </div>

        <!-- Desktop table -->
        <div class="mt-4 hidden overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 lg:block">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input
                                type="checkbox" :checked="store.allSelected"
                                class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                                @change="store.toggleSelectAll()"
                            />
                        </th>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Recipient</th>
                        <th class="px-4 py-3">Template</th>
                        <th class="px-4 py-3">Issued</th>
                        <th class="px-4 py-3">Expires</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="store.loading">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">Loading…</td>
                    </tr>
                    <tr v-else-if="!store.items.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No certificates found.</td>
                    </tr>
                    <tr v-for="c in store.items" v-else :key="c.uuid" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <td class="px-4 py-3">
                            <input
                                v-model="store.selected" type="checkbox" :value="c.uuid"
                                class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                            />
                        </td>
                        <td class="px-4 py-3 font-mono text-xs font-medium text-slate-900 dark:text-slate-100">{{ c.certificate_number }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ recipientName(c) }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ c.recipient?.email }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ c.template?.name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ c.issue_date?.slice(0, 10) }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ c.expiry_date?.slice(0, 10) || 'Never' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="showingDeleted ? 'deleted' : c.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <ActionDropdown :actions="actionsFor(c)" @action="handle(c, $event)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="mt-4 space-y-3 lg:hidden">
            <p v-if="store.loading" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</p>
            <p v-else-if="!store.items.length" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">No certificates found.</p>
            <div
                v-for="c in store.items" v-else :key="c.uuid"
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="flex items-start gap-3">
                    <input
                        v-model="store.selected" type="checkbox" :value="c.uuid"
                        class="mt-1 rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">{{ c.certificate_number }}</p>
                            <StatusBadge :status="showingDeleted ? 'deleted' : c.status" />
                        </div>
                        <p class="mt-1 truncate font-medium text-slate-900 dark:text-slate-100">{{ recipientName(c) }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ c.template?.name }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Issued {{ c.issue_date?.slice(0, 10) }} · expires {{ c.expiry_date?.slice(0, 10) || 'never' }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <ActionDropdown :actions="actionsFor(c)" @action="handle(c, $event)" />
                </div>
            </div>
        </div>

        <AppPagination :meta="store.meta" @change="(p) => { store.filters.page = p; store.fetch(); }" />

        <ConfirmDialog
            :open="!!confirm"
            :title="confirm?.title"
            :message="confirm?.message"
            :confirm-label="confirm?.confirmLabel"
            @confirm="confirm.run()"
            @cancel="confirm = null"
        />

        <RenewDialog
            :open="!!renewing"
            :certificate="renewing"
            @close="renewing = null"
            @confirm="confirmRenew"
        />
    </div>
</template>
