<script setup>
import { onMounted, reactive, ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { useUiStore } from '@/stores/ui';
import AppPagination from '@/components/ui/AppPagination.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';

const ui = useUiStore();

const notes = ref([]);
const meta = ref(null);
const loading = ref(true);
const page = ref(1);

const modal = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const deleting = ref(null);
const form = reactive({ version: '', title: '', released_on: '', body: '' });

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/release-notes', { params: { page: page.value } });
        notes.value = data.data;
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    Object.assign(form, { version: '', title: '', released_on: new Date().toISOString().slice(0, 10), body: '' });
    errors.value = {};
    modal.value = true;
}

function openEdit(note) {
    editing.value = note;
    Object.assign(form, {
        version: note.version,
        title: note.title || '',
        released_on: note.released_on?.slice(0, 10) || '',
        body: note.body,
    });
    errors.value = {};
    modal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        await ensureCsrf();
        if (editing.value) {
            await http.put(`/admin/release-notes/${editing.value.uuid}`, form);
            ui.success('Version updated.');
        } else {
            await http.post('/admin/release-notes', form);
            ui.success('Version published.');
        }
        modal.value = false;
        load();
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) ui.error('Could not save the version.');
    } finally {
        saving.value = false;
    }
}

async function confirmDelete() {
    try {
        await ensureCsrf();
        await http.delete(`/admin/release-notes/${deleting.value.uuid}`);
        ui.success('Version deleted.');
        load();
    } catch {
        ui.error('Could not delete the version.');
    } finally {
        deleting.value = null;
    }
}

function lines(body) {
    return (body || '').split(/\r?\n/).map((l) => l.replace(/^\s*[-•*]\s?/, '').trim()).filter(Boolean);
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Version Control</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Publish a version number and what changed. It shows across the app as a clickable badge.
                </p>
            </div>
            <button class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">
                + New version
            </button>
        </div>

        <div v-if="loading" class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        <div v-else-if="!notes.length" class="mt-10 rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
            <p class="font-medium text-slate-900 dark:text-slate-100">No versions yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Publish your first version to start the changelog.</p>
        </div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="note in notes" :key="note.uuid" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="rounded-md bg-brand-600 px-2 py-0.5 text-sm font-semibold text-white">v{{ note.version }}</span>
                        <span v-if="note.title" class="font-medium text-slate-900 dark:text-slate-100">{{ note.title }}</span>
                        <span class="text-xs text-slate-400">{{ note.released_on?.slice(0, 10) }}</span>
                    </div>
                    <div class="flex shrink-0 gap-2 text-sm">
                        <button class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" @click="openEdit(note)">Edit</button>
                        <button class="font-medium text-rose-600 hover:text-rose-700 dark:text-rose-400" @click="deleting = note">Delete</button>
                    </div>
                </div>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                    <li v-for="(line, i) in lines(note.body)" :key="i" class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400" />
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <AppPagination :meta="meta" @change="(p) => { page = p; load(); }" />

        <!-- Create / edit -->
        <teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-slate-900/50" @click="modal = false" />
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit version' : 'New version' }}</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="save">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Version number</label>
                                <input v-model="form.version" type="text" placeholder="1.2.0" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700" />
                                <p v-if="errors.version" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.version[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Release date</label>
                                <input v-model="form.released_on" type="date" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Summary (optional)</label>
                            <input v-model="form.title" type="text" placeholder="e.g. Bulk actions & dark mode" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Changes</label>
                            <textarea v-model="form.body" rows="7" required placeholder="One change per line, e.g.&#10;- Added bulk invite and delete on recipients&#10;- Faster page navigation" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700" />
                            <p class="mt-1 text-xs text-slate-400">One change per line — they show as a bulleted list.</p>
                            <p v-if="errors.body" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.body[0] }}</p>
                        </div>
                        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:justify-end">
                            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60" @click="modal = false">Cancel</button>
                            <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                                {{ saving ? 'Saving…' : editing ? 'Save changes' : 'Publish version' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <ConfirmDialog
            :open="!!deleting"
            title="Delete version?"
            :message="`v${deleting?.version} will be removed from the changelog.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
