<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { apiBase } from '@/api/area';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();

const team = ref(null);
const loading = ref(true);
const invite = reactive({ name: '', email: '' });
const errors = ref({});
const inviting = ref(false);

const full = computed(() => team.value?.limit != null && team.value.used >= team.value.limit);

async function load() {
    const { data } = await http.get(`${apiBase()}/team`);
    team.value = data;
}

onMounted(async () => {
    try {
        await load();
    } finally {
        loading.value = false;
    }
});

async function sendInvite() {
    inviting.value = true;
    errors.value = {};
    try {
        await ensureCsrf();
        await http.post(`${apiBase()}/team`, { name: invite.name, email: invite.email });
        invite.name = '';
        invite.email = '';
        await load();
        ui.success('Teammate invited — a setup link was emailed.');
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            ui.error(e.response?.data?.message || 'Could not invite the teammate.');
        }
    } finally {
        inviting.value = false;
    }
}

async function removeMember(member) {
    if (!confirm(`Remove ${member.name} (${member.email})?`)) return;
    try {
        await ensureCsrf();
        await http.delete(`${apiBase()}/team/${member.uuid}`);
        await load();
        ui.success('Teammate removed.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not remove the teammate.');
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Team</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Certificate admins can sign in and manage this organization.
        </p>

        <div v-if="team" class="mt-6 rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Certificate admins</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    {{ team.used }}<template v-if="team.limit != null"> / {{ team.limit }}</template> used
                </span>
            </div>

            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="member in team.data" :key="member.uuid" class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ member.name }}
                            <span v-if="member.is_primary" class="ml-1 rounded-full bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">Primary</span>
                            <span v-if="!member.active" class="ml-1 rounded-full bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:text-amber-300">Pending</span>
                        </p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ member.email }}</p>
                    </div>
                    <button v-if="!member.is_primary" type="button" class="shrink-0 text-sm font-medium text-rose-600 hover:text-rose-700" @click="removeMember(member)">
                        Remove
                    </button>
                </li>
            </ul>

            <form class="mt-4 flex flex-col gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row" @submit.prevent="sendInvite">
                <div class="flex-1">
                    <input v-model="invite.name" type="text" placeholder="Full name" required :disabled="full"
                        class="block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:opacity-50" />
                    <p v-if="errors.name" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ errors.name[0] }}</p>
                </div>
                <div class="flex-1">
                    <input v-model="invite.email" type="email" placeholder="Email" required :disabled="full"
                        class="block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:opacity-50" />
                    <p v-if="errors.email" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ errors.email[0] }}</p>
                </div>
                <button type="submit" :disabled="inviting || full"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ inviting ? 'Inviting…' : 'Invite' }}
                </button>
            </form>
            <p v-if="full" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                You've reached your limit of {{ team.limit }} certificate admin(s). Contact your platform administrator to raise it.
            </p>
        </div>

        <p v-else-if="loading" class="mt-6 text-sm text-slate-500 dark:text-slate-400">Loading…</p>
    </div>
</template>
