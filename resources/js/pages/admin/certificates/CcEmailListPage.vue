<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useCertificateCcEmailsStore } from '@/stores/certificateCcEmails';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import { useOrgFilter } from '@/composables/useOrgFilter';
import { rn } from '@/api/area';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import OrgFilterBanner from '@/components/ui/OrgFilterBanner.vue';

const store = useCertificateCcEmailsStore();
const ui = useUiStore();
const auth = useAuthStore();
const orgFilter = useOrgFilter(store);

const modal = ref(false);
const editing = ref(null);
const deleting = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({ email: '', name: '' });

onMounted(() => store.fetch());

function search() {
    store.filters.page = 1;
    store.fetch();
}

function openCreate() {
    editing.value = null;
    Object.assign(form, { email: '', name: '' });
    errors.value = {};
    modal.value = true;
}

function openEdit(ccEmail) {
    editing.value = ccEmail;
    Object.assign(form, { email: ccEmail.email, name: ccEmail.name || '' });
    errors.value = {};
    modal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (editing.value) {
            await store.update(editing.value.uuid, form);
            ui.success('CC recipient updated.');
        } else {
            await store.create(form);
            ui.success('CC recipient added.');
        }
        modal.value = false;
        store.fetch();
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) ui.error('Could not save the CC recipient.');
    } finally {
        saving.value = false;
    }
}

async function confirmDelete() {
    try {
        await store.destroy(deleting.value.uuid);
        ui.success('CC recipient removed.');
    } catch {
        ui.error('Could not remove the CC recipient.');
    } finally {
        deleting.value = null;
    }
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <router-link
                        :to="{ name: rn('certificates') }"
                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                        &larr; Credentials
                    </router-link>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Certificate CC recipients</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    These addresses are copied on every certificate email (issued and revoked).
                    When none are set, the organization's own contact address is copied by default.
                </p>
            </div>
            <button class="shrink-0 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">
                + Add recipient
            </button>
        </div>

        <div class="mt-6">
            <DebouncedSearchInput v-model="store.filters.q" placeholder="Search by email or name…" @search="search" />
        </div>

        <OrgFilterBanner class="mt-4" :active="orgFilter.active.value" :org-name="orgFilter.orgName.value" @clear="orgFilter.clear" />

        <div v-if="store.loading" class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        <div v-else-if="!store.items.length" class="mt-10 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
            <p class="font-medium text-slate-900 dark:text-slate-100">No CC recipients yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add an address to copy it on every certificate email.</p>
        </div>

        <div v-else class="mt-6 overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Name</th>
                        <th v-if="auth.isAdmin" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Organization</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="ccEmail in store.items" :key="ccEmail.uuid">
                        <td class="px-5 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">{{ ccEmail.email }}</td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">{{ ccEmail.name || '—' }}</td>
                        <td v-if="auth.isAdmin" class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">{{ ccEmail.organization?.name || 'Platform (admin)' }}</td>
                        <td class="px-5 py-3 text-right text-sm">
                            <button class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="openEdit(ccEmail)">
                                Edit
                            </button>
                            <button class="ml-2 rounded-lg border border-rose-200 dark:border-rose-900 px-3 py-1.5 font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40" @click="deleting = ccEmail">
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination :meta="store.meta" @change="(p) => { store.filters.page = p; store.fetch(); }" />

        <teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-slate-900/50" @click="modal = false" />
                <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit CC recipient' : 'Add CC recipient' }}</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="save">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                            <input v-model="form.email" type="email" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            <p v-if="errors.email" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.email[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name <span class="text-slate-400">(optional)</span></label>
                            <input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            <p v-if="errors.name" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.name[0] }}</p>
                        </div>
                        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                            <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="modal = false">Cancel</button>
                            <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                                {{ saving ? 'Saving…' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <ConfirmDialog
            :open="!!deleting"
            title="Remove CC recipient?"
            :message="`'${deleting?.email}' will no longer be copied on certificate emails.`"
            confirm-label="Remove"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
