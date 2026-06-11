<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/api/http';
import { useGroupsStore } from '@/stores/recipients';
import { useUiStore } from '@/stores/ui';
import DebouncedSearchInput from '@/components/ui/DebouncedSearchInput.vue';
import AppPagination from '@/components/ui/AppPagination.vue';
import BulkAddRecipientsModal from '@/components/recipients/BulkAddRecipientsModal.vue';

const route = useRoute();
const groupsStore = useGroupsStore();
const ui = useUiStore();

const group = ref(null);
const members = ref([]);
const meta = ref(null);
const memberQuery = ref('');
const page = ref(1);

const addModal = ref(false);
const bulkModal = ref(false);
const candidates = ref([]);
const candidateQuery = ref('');
const selectedUuids = ref([]);

async function loadGroup() {
    group.value = await groupsStore.fetchOne(route.params.uuid);
}

async function loadMembers() {
    const { data } = await http.get('/admin/recipients', {
        params: { group: route.params.uuid, q: memberQuery.value, page: page.value },
    });
    members.value = data.data;
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
}

async function loadCandidates() {
    const { data } = await http.get('/admin/recipients', {
        params: { q: candidateQuery.value, per_page: 20 },
    });
    candidates.value = data.data.filter((r) => !r.groups?.some((g) => g.uuid === route.params.uuid));
}

onMounted(() => {
    loadGroup();
    loadMembers();
});

async function addSelected() {
    if (!selectedUuids.value.length) return;
    try {
        await groupsStore.addRecipients(route.params.uuid, selectedUuids.value);
        ui.success(`${selectedUuids.value.length} recipient(s) added.`);
        selectedUuids.value = [];
        addModal.value = false;
        loadGroup();
        loadMembers();
    } catch {
        ui.error('Could not add recipients.');
    }
}

async function remove(recipient) {
    try {
        await groupsStore.removeRecipient(route.params.uuid, recipient.uuid);
        ui.success(`${recipient.full_name} removed from group.`);
        loadGroup();
        loadMembers();
    } catch {
        ui.error('Could not remove the recipient.');
    }
}
</script>

<template>
    <div v-if="group">
        <router-link :to="{ name: 'admin.groups' }" class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
            ← Back to groups
        </router-link>
        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ group.name }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ group.description || 'No description' }} · {{ group.recipients_count }} member(s)</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                    @click="bulkModal = true"
                >
                    Bulk add new
                </button>
                <button
                    class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700"
                    @click="addModal = true; loadCandidates();"
                >
                    + Add existing
                </button>
            </div>
        </div>

        <div class="mt-6">
            <DebouncedSearchInput v-model="memberQuery" placeholder="Search members…" @search="page = 1; loadMembers();" />
        </div>

        <div class="mt-4 space-y-2">
            <p v-if="!members.length" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                No members in this group yet.
            </p>
            <div
                v-for="member in members"
                :key="member.uuid"
                class="flex items-center justify-between gap-3 rounded-xl bg-white dark:bg-slate-900 px-4 py-3 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-900 dark:text-slate-100">{{ member.full_name }}</p>
                    <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ member.email }}</p>
                </div>
                <button class="shrink-0 text-sm font-medium text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300" @click="remove(member)">
                    Remove
                </button>
            </div>
        </div>

        <AppPagination :meta="meta" @change="(p) => { page = p; loadMembers(); }" />

        <teleport to="body">
            <div v-if="addModal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-slate-900/50" @click="addModal = false" />
                <div class="relative flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Add recipients to {{ group.name }}</h2>
                    <div class="mt-4">
                        <DebouncedSearchInput v-model="candidateQuery" placeholder="Search recipients…" @search="loadCandidates" />
                    </div>
                    <div class="mt-3 min-h-0 flex-1 space-y-1 overflow-y-auto">
                        <p v-if="!candidates.length" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No matching recipients.</p>
                        <label
                            v-for="candidate in candidates"
                            :key="candidate.uuid"
                            class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <input
                                v-model="selectedUuids"
                                type="checkbox"
                                :value="candidate.uuid"
                                class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                            />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ candidate.full_name }}</span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ candidate.email }}</span>
                            </span>
                        </label>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row sm:justify-end">
                        <button class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="addModal = false">
                            Cancel
                        </button>
                        <button
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                            :disabled="!selectedUuids.length"
                            @click="addSelected"
                        >
                            Add {{ selectedUuids.length || '' }} selected
                        </button>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
    <div v-else class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">Loading group…</div>

    <BulkAddRecipientsModal
        :open="bulkModal"
        :fixed-group="group"
        @close="bulkModal = false"
        @done="loadGroup(); loadMembers();"
    />
</template>
