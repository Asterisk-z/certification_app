<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRecipientsStore, useGroupsStore } from '@/stores/recipients';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import { useOrgFilter } from '@/composables/useOrgFilter';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import OrgFilterBanner from '@/components/ui/OrgFilterBanner.vue';
import BulkAddRecipientsModal from '@/components/recipients/BulkAddRecipientsModal.vue';

const store = useRecipientsStore();
const groupsStore = useGroupsStore();
const ui = useUiStore();
const auth = useAuthStore();
const orgFilter = useOrgFilter(store);

const groups = ref([]);
const deleting = ref(null);
const modal = ref(false);
const bulkModal = ref(false);
const bulkConfirm = ref(null); // { title, message, confirmLabel, run }
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({ full_name: '', email: '', phone: '', group_uuids: [] });

onMounted(async () => {
    store.fetch();
    groups.value = await groupsStore.fetchAll();
});

function search() {
    store.filters.page = 1;
    store.fetch();
}

function openCreate() {
    editing.value = null;
    Object.assign(form, { full_name: '', email: '', phone: '', group_uuids: [] });
    errors.value = {};
    modal.value = true;
}

function openEdit(recipient) {
    editing.value = recipient;
    Object.assign(form, {
        full_name: recipient.full_name,
        email: recipient.email,
        phone: recipient.phone || '',
        group_uuids: [],
    });
    errors.value = {};
    modal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (editing.value) {
            await store.update(editing.value.uuid, form);
            ui.success('Recipient updated.');
        } else {
            await store.create(form);
            ui.success('Recipient added and invite sent.');
        }
        modal.value = false;
        store.fetch();
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) ui.error('Could not save the recipient.');
    } finally {
        saving.value = false;
    }
}

async function sendInvite(recipient) {
    try {
        const result = await store.invite(recipient.uuid);
        ui.success(result.message);
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not send the invite.');
    }
}

async function confirmDelete() {
    try {
        await store.destroy(deleting.value.uuid);
        ui.success('Recipient removed.');
    } catch {
        ui.error('Could not remove the recipient.');
    } finally {
        deleting.value = null;
    }
}

function bulk(action) {
    const count = store.selected.length;
    const labels = {
        invite: {
            title: 'Send invites?',
            message: `${count} recipient(s) will be emailed a link to set up their portal access.`,
            confirmLabel: 'Send invites',
        },
        delete: {
            title: 'Remove selected?',
            message: `${count} recipient(s) will be removed. Their issued credentials are kept.`,
            confirmLabel: 'Remove',
        },
    };

    bulkConfirm.value = {
        ...labels[action],
        run: async () => {
            try {
                const result = await store.bulkAction(action);
                ui.success(result.message);
                store.fetch();
            } catch (e) {
                ui.error(e.response?.data?.message || 'Bulk action failed.');
            } finally {
                bulkConfirm.value = null;
            }
        },
    };
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Recipients</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">People who can receive credentials.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                    @click="bulkModal = true"
                >
                    Bulk add
                </button>
                <button
                    class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700"
                    @click="openCreate"
                >
                    + Add recipient
                </button>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <DebouncedSearchInput v-model="store.filters.q" placeholder="Search name or email…" @search="search" />
            <select
                v-model="store.filters.group"
                class="rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                @change="search"
            >
                <option value="">All groups</option>
                <option v-for="g in groups" :key="g.uuid" :value="g.uuid">{{ g.name }}</option>
            </select>
        </div>

        <OrgFilterBanner class="mt-4" :active="orgFilter.active.value" :org-name="orgFilter.orgName.value" @clear="orgFilter.clear" />

        <!-- Bulk action bar -->
        <div
            v-if="store.selected.length"
            class="sticky top-2 z-20 mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-lg dark:bg-slate-800"
        >
            <span class="font-medium">{{ store.selected.length }} selected</span>
            <span class="hidden text-slate-400 sm:inline">·</span>
            <button class="rounded-lg bg-emerald-600 px-3 py-1.5 font-medium hover:bg-emerald-500" @click="bulk('invite')">Invite</button>
            <button class="rounded-lg bg-rose-600 px-3 py-1.5 font-medium hover:bg-rose-500" @click="bulk('delete')">Delete</button>
            <button class="ml-auto text-slate-300 hover:text-white" @click="store.selected = []">Clear</button>
        </div>

        <!-- Desktop table -->
        <div class="mt-4 hidden overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 md:block">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input
                                type="checkbox" :checked="store.allSelected"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
                                @change="store.toggleSelectAll()"
                            />
                        </th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th v-if="auth.isAdmin" class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Groups</th>
                        <th class="px-4 py-3">Credentials</th>
                        <th class="px-4 py-3">Portal</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="store.loading">
                        <td :colspan="auth.isAdmin ? 8 : 7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Loading…</td>
                    </tr>
                    <tr v-else-if="!store.items.length">
                        <td :colspan="auth.isAdmin ? 8 : 7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No recipients found.</td>
                    </tr>
                    <tr v-for="r in store.items" v-else :key="r.uuid" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <td class="px-4 py-3">
                            <input
                                v-model="store.selected" type="checkbox" :value="r.uuid"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
                            />
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ r.full_name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ r.email }}</td>
                        <td v-if="auth.isAdmin" class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ r.organization?.name || 'Platform' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                            <span v-if="!r.groups?.length" class="text-slate-400">—</span>
                            <span v-else>{{ r.groups.map((g) => g.name).join(', ') }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ r.certificates_count }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="r.user_id ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                            >
                                {{ r.user_id ? 'Active' : 'Not invited' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="!r.user_id" class="font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300" @click="sendInvite(r)">Invite</button>
                            <button class="ml-3 font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300" @click="openEdit(r)">Edit</button>
                            <button class="ml-3 font-medium text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300" @click="deleting = r">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="mt-6 space-y-3 md:hidden">
            <p v-if="store.loading" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</p>
            <p v-else-if="!store.items.length" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No recipients found.</p>
            <div
                v-for="r in store.items"
                v-else
                :key="r.uuid"
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="flex items-start gap-3">
                    <input
                        v-model="store.selected" type="checkbox" :value="r.uuid"
                        class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ r.full_name }}</p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ r.email }}</p>
                        <p v-if="auth.isAdmin" class="truncate text-xs font-medium text-slate-400 dark:text-slate-500">{{ r.organization?.name || 'Platform' }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="r.user_id ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                    >
                        {{ r.user_id ? 'Active' : 'Not invited' }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ r.certificates_count }} credential(s)
                    <template v-if="r.groups?.length"> · {{ r.groups.map((g) => g.name).join(', ') }}</template>
                </p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <button v-if="!r.user_id" class="rounded-lg border border-emerald-300 dark:border-emerald-800 px-3 py-1.5 font-medium text-emerald-700 dark:text-emerald-400" @click="sendInvite(r)">Invite</button>
                    <button class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300" @click="openEdit(r)">Edit</button>
                    <button class="rounded-lg border border-rose-200 dark:border-rose-900 px-3 py-1.5 font-medium text-rose-600 dark:text-rose-400" @click="deleting = r">Delete</button>
                </div>
            </div>
        </div>

        <AppPagination :meta="store.meta" @change="(p) => { store.filters.page = p; store.fetch(); }" />

        <!-- Create/edit modal -->
        <teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-slate-900/50" @click="modal = false" />
                <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit recipient' : 'Add recipient' }}</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="save">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Full name</label>
                            <input v-model="form.full_name" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            <p v-if="errors.full_name" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.full_name[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                            <input v-model="form.email" type="email" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            <p v-if="errors.email" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.email[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Phone (optional)</label>
                            <input v-model="form.phone" type="text" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                        <div v-if="!editing && groups.length">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Add to groups</label>
                            <select
                                v-model="form.group_uuids" multiple
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option v-for="g in groups" :key="g.uuid" :value="g.uuid">{{ g.name }}</option>
                            </select>
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

        <BulkAddRecipientsModal
            :open="bulkModal"
            :groups="groups"
            @close="bulkModal = false"
            @done="store.fetch()"
        />

        <ConfirmDialog
            :open="!!deleting"
            title="Remove recipient?"
            :message="`${deleting?.full_name} will be removed. Their issued credentials are kept.`"
            confirm-label="Remove"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />

        <ConfirmDialog
            :open="!!bulkConfirm"
            :title="bulkConfirm?.title"
            :message="bulkConfirm?.message"
            :confirm-label="bulkConfirm?.confirmLabel"
            :danger="bulkConfirm?.confirmLabel !== 'Send invites'"
            @confirm="bulkConfirm.run()"
            @cancel="bulkConfirm = null"
        />
    </div>
</template>
