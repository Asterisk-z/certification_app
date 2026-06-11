<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useGroupsStore } from '@/stores/recipients';
import { useUiStore } from '@/stores/ui';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';

const store = useGroupsStore();
const ui = useUiStore();

const modal = ref(false);
const editing = ref(null);
const deleting = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({ name: '', description: '' });

onMounted(() => store.fetch());

function search() {
    store.filters.page = 1;
    store.fetch();
}

function openCreate() {
    editing.value = null;
    Object.assign(form, { name: '', description: '' });
    errors.value = {};
    modal.value = true;
}

function openEdit(group) {
    editing.value = group;
    Object.assign(form, { name: group.name, description: group.description || '' });
    errors.value = {};
    modal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (editing.value) {
            await store.update(editing.value.uuid, form);
            ui.success('Group updated.');
        } else {
            await store.create(form);
            ui.success('Group created.');
        }
        modal.value = false;
        store.fetch();
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) ui.error('Could not save the group.');
    } finally {
        saving.value = false;
    }
}

async function confirmDelete() {
    try {
        await store.destroy(deleting.value.uuid);
        ui.success('Group deleted.');
    } catch {
        ui.error('Could not delete the group.');
    } finally {
        deleting.value = null;
    }
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Groups</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Organise recipients to send certificates in bulk.</p>
            </div>
            <button class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">
                + New group
            </button>
        </div>

        <div class="mt-6">
            <DebouncedSearchInput v-model="store.filters.q" placeholder="Search groups…" @search="search" />
        </div>

        <div v-if="store.loading" class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        <div v-else-if="!store.items.length" class="mt-10 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
            <p class="font-medium text-slate-900 dark:text-slate-100">No groups yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a group, then add recipients to it.</p>
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="group in store.items" :key="group.uuid" class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ group.name }}</h2>
                <p class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ group.description || 'No description' }}</p>
                <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-300">{{ group.recipients_count }} recipient(s)</p>
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    <router-link
                        :to="{ name: 'admin.groups.detail', params: { uuid: group.uuid } }"
                        class="rounded-lg bg-brand-600 px-3 py-1.5 font-medium text-white hover:bg-brand-700"
                    >
                        Manage members
                    </router-link>
                    <button class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="openEdit(group)">
                        Edit
                    </button>
                    <button class="rounded-lg border border-rose-200 dark:border-rose-900 px-3 py-1.5 font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40" @click="deleting = group">
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <AppPagination :meta="store.meta" @change="(p) => { store.filters.page = p; store.fetch(); }" />

        <teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-slate-900/50" @click="modal = false" />
                <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit group' : 'New group' }}</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="save">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            <p v-if="errors.name" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.name[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                            <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
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
            title="Delete group?"
            :message="`'${deleting?.name}' will be deleted. Recipients in it are not removed.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
