<script setup>
import { onMounted } from 'vue';
import { useOrganizationsStore } from '@/stores/organizations';
import { useUiStore } from '@/stores/ui';
import AppPagination from '@/components/ui/AppPagination.vue';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';

const store = useOrganizationsStore();
const ui = useUiStore();

onMounted(() => store.fetch());

function search(value) {
    store.filters.q = value;
    store.filters.page = 1;
    store.fetch();
}

function changePage(page) {
    store.filters.page = page;
    store.fetch();
}

async function remove(org) {
    if (!window.confirm(`Deactivate and remove “${org.name}”? Their login will stop working.`)) return;
    try {
        await store.destroy(org.uuid);
        ui.success('Organization removed.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not remove the organization.');
    }
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Organizations</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tenants that manage their own templates, recipients and credentials.</p>
            </div>
            <router-link :to="{ name: 'admin.organizations.create' }" class="rounded-lg bg-brand-600 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-brand-700">
                + New organization
            </router-link>
        </div>

        <div class="mt-6 max-w-sm">
            <DebouncedSearchInput placeholder="Search name, email or code…" @search="search" />
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 sm:px-6">Organization</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Templates</th>
                            <th class="px-4 py-3 text-right">Recipients</th>
                            <th class="px-4 py-3 text-right">Credentials</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="org in store.items" :key="org.uuid" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <img v-if="org.logo_path" :src="`/storage/${org.logo_path}`" class="h-8 w-8 rounded object-contain bg-white ring-1 ring-slate-200" alt="" />
                                    <div>
                                        <router-link :to="{ name: 'admin.organizations.detail', params: { uuid: org.uuid } }" class="font-medium text-slate-900 dark:text-slate-100 hover:text-brand-600">{{ org.name }}</router-link>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ org.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">{{ org.code }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="org.status === 'active' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'">
                                    {{ org.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ org.templates_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ org.recipients_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ org.certificates_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <router-link :to="{ name: 'admin.organizations.detail', params: { uuid: org.uuid } }" class="text-sm font-medium text-brand-600 hover:text-brand-700">View</router-link>
                                <router-link :to="{ name: 'admin.organizations.edit', params: { uuid: org.uuid } }" class="ml-3 text-sm font-medium text-slate-600 hover:text-slate-700 dark:text-slate-300">Edit</router-link>
                                <button class="ml-3 text-sm font-medium text-rose-600 hover:text-rose-700" @click="remove(org)">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!store.loading && !store.items.length">
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No organizations yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AppPagination v-if="store.meta" :meta="store.meta" class="mt-4" @change="changePage" />
    </div>
</template>
