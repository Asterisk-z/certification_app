<script setup>
import { onMounted, ref } from 'vue';
import { useTemplatesStore } from '@/stores/templates';
import { useUiStore } from '@/stores/ui';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';

const store = useTemplatesStore();
const ui = useUiStore();
const deleting = ref(null);

onMounted(() => store.fetch());

function search() {
    store.filters.page = 1;
    store.fetch();
}

function page(p) {
    store.filters.page = p;
    store.fetch();
}

async function duplicate(template) {
    try {
        const copy = await store.duplicate(template.uuid);
        ui.success(`Duplicated as ${copy.name}.`);
        store.fetch();
    } catch {
        ui.error('Could not duplicate the template.');
    }
}

async function confirmDelete() {
    try {
        await store.destroy(deleting.value.uuid);
        ui.success('Template deleted.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not delete the template.');
    } finally {
        deleting.value = null;
    }
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Templates</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Design credential templates.</p>
            </div>
            <router-link
                :to="{ name: 'admin.templates.create' }"
                class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700"
            >
                + New template
            </router-link>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <DebouncedSearchInput v-model="store.filters.q" placeholder="Search name or code…" @search="search" />
            <select
                v-model="store.filters.status"
                class="rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                @change="search"
            >
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="ready">Ready</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <div v-if="store.loading" class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">Loading templates…</div>

        <div v-else-if="!store.items.length" class="mt-10 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
            <p class="font-medium text-slate-900 dark:text-slate-100">No templates yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create your first credential template to get started.</p>
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="template in store.items"
                :key="template.uuid"
                class="group overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="relative aspect-[1123/794] bg-slate-100 dark:bg-slate-800">
                    <img
                        v-if="template.background_image"
                        :src="`/storage/${template.background_image}`"
                        :alt="template.name"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">No background</div>
                    <StatusBadge :status="template.status" class="absolute right-3 top-3" />
                </div>
                <div class="p-4">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ template.name }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ template.code }} · {{ template.certificates_count }} issued
                            <template v-if="template.duration"> · valid {{ template.duration }} {{ template.duration_type }}(s)</template>
                        </p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-sm">
                        <router-link
                            :to="{ name: 'admin.templates.designer', params: { uuid: template.uuid } }"
                            class="rounded-lg bg-brand-600 px-3 py-1.5 font-medium text-white hover:bg-brand-700"
                        >
                            Designer
                        </router-link>
                        <router-link
                            :to="{ name: 'admin.templates.edit', params: { uuid: template.uuid } }"
                            class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            Edit
                        </router-link>
                        <button
                            class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                            @click="duplicate(template)"
                        >
                            Duplicate
                        </button>
                        <button
                            class="rounded-lg border border-rose-200 dark:border-rose-900 px-3 py-1.5 font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                            @click="deleting = template"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <AppPagination :meta="store.meta" @change="page" />

        <ConfirmDialog
            :open="!!deleting"
            title="Delete template?"
            :message="`'${deleting?.name}' will be removed. Credentials already issued from it are kept.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
