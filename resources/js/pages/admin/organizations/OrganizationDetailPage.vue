<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useOrganizationsStore } from '@/stores/organizations';
import { useUiStore } from '@/stores/ui';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const FEATURES = [
    { key: 'templates', label: 'Templates', route: 'admin.templates' },
    { key: 'certificates', label: 'Credentials', route: 'admin.certificates' },
    { key: 'recipients', label: 'Recipients', route: 'admin.recipients' },
    { key: 'groups', label: 'Groups', route: 'admin.groups' },
];

const statusOrder = ['pending', 'queued', 'sent', 'failed', 'revoked', 'expired', 'renewed', 'cancelled'];

const route = useRoute();
const store = useOrganizationsStore();
const ui = useUiStore();

const org = ref(null);
const stats = ref(null);
const savingSettings = ref(false);

// Toggle models, kept in sync with the loaded org.
const features = reactive({ templates: true, certificates: true, recipients: true, groups: true });

// Certificate-admin team.
const team = ref(null);
const invite = reactive({ name: '', email: '' });
const inviteErrors = ref({});
const inviting = ref(false);

onMounted(async () => {
    org.value = await store.fetchOne(route.params.uuid);
    Object.assign(features, { templates: true, certificates: true, recipients: true, groups: true, ...(org.value.features || {}) });
    stats.value = await store.fetchStats(route.params.uuid);
    team.value = await store.fetchTeam(route.params.uuid);
});

async function loadTeam() {
    team.value = await store.fetchTeam(route.params.uuid);
}

async function sendInvite() {
    inviting.value = true;
    inviteErrors.value = {};
    try {
        await store.inviteTeam(route.params.uuid, { name: invite.name, email: invite.email });
        invite.name = '';
        invite.email = '';
        await loadTeam();
        ui.success('Certificate admin invited — a setup link was emailed.');
    } catch (e) {
        inviteErrors.value = e.response?.data?.errors || {};
        if (!Object.keys(inviteErrors.value).length) {
            ui.error(e.response?.data?.message || 'Could not invite the admin.');
        }
    } finally {
        inviting.value = false;
    }
}

async function removeMember(member) {
    if (!confirm(`Remove ${member.name} (${member.email})?`)) return;
    try {
        await store.removeTeam(route.params.uuid, member.uuid);
        await loadTeam();
        ui.success('Certificate admin removed.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not remove the admin.');
    }
}

// Links carry the org so the destination list filters to it.
function listLink(routeName) {
    return { name: routeName, query: { organization: org.value.uuid, org_name: org.value.name } };
}

const isActive = computed(() => org.value?.status === 'active');

async function persist(payload, message) {
    savingSettings.value = true;
    try {
        org.value = await store.updateSettings(route.params.uuid, payload);
        ui.success(message);
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not update settings.');
    } finally {
        savingSettings.value = false;
    }
}

function toggleStatus() {
    const next = isActive.value ? 'inactive' : 'active';
    persist({ status: next }, `Organization ${next === 'active' ? 'activated' : 'deactivated'}.`);
}

function toggleFeature(key) {
    features[key] = !features[key];
    persist({ features: { ...features } }, 'Feature access updated.');
}

const totalsCards = computed(() => [
    { key: 'certificates', label: 'Credentials', route: 'admin.certificates' },
    { key: 'recipients', label: 'Recipients', route: 'admin.recipients' },
    { key: 'templates', label: 'Templates', route: 'admin.templates' },
    { key: 'groups', label: 'Groups', route: 'admin.groups' },
]);
</script>

<template>
    <div v-if="org" class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <img v-if="org.logo_path" :src="`/storage/${org.logo_path}`" class="h-14 w-14 rounded-lg bg-white object-contain ring-1 ring-slate-200" alt="" />
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ org.name }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ org.email }} · <span class="font-mono">{{ org.code }}</span></p>
                </div>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="isActive ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'">
                    {{ org.status }}
                </span>
            </div>
            <router-link :to="{ name: 'admin.organizations.edit', params: { uuid: org.uuid } }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-center text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">Edit profile</router-link>
        </div>

        <p v-if="org.description" class="text-sm text-slate-600 dark:text-slate-400">{{ org.description }}</p>

        <!-- Status + feature toggles -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Access</h2>
                <span v-if="savingSettings" class="text-xs text-slate-400">Saving…</span>
            </div>
            <div class="mt-4 flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Account status</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Inactive organizations cannot sign in.</p>
                </div>
                <button
                    type="button" role="switch" :aria-checked="isActive" :disabled="savingSettings"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50"
                    :class="isActive ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'"
                    @click="toggleStatus"
                >
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform" :class="isActive ? 'translate-x-5' : 'translate-x-0.5'" />
                </button>
            </div>
            <div class="mt-4">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Allowed features</p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label v-for="f in FEATURES" :key="f.key" class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ f.label }}</span>
                        <button
                            type="button" role="switch" :aria-checked="features[f.key]" :disabled="savingSettings"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50"
                            :class="features[f.key] ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-700'"
                            @click="toggleFeature(f.key)"
                        >
                            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform" :class="features[f.key] ? 'translate-x-5' : 'translate-x-0.5'" />
                        </button>
                    </label>
                </div>
            </div>
        </div>

        <!-- Certificate-admin team -->
        <div v-if="team" class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
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
                    <input v-model="invite.name" type="text" placeholder="Full name" required
                        class="block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <p v-if="inviteErrors.name" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ inviteErrors.name[0] }}</p>
                </div>
                <div class="flex-1">
                    <input v-model="invite.email" type="email" placeholder="Email" required
                        class="block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <p v-if="inviteErrors.email" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ inviteErrors.email[0] }}</p>
                </div>
                <button type="submit" :disabled="inviting"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ inviting ? 'Inviting…' : 'Invite admin' }}
                </button>
            </form>
            <p v-if="team.limit != null && team.used >= team.limit" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                This organization is at its configured certificate-admin limit ({{ team.limit }}). As an admin you can still add more.
            </p>
        </div>

        <!-- Stat cards (links to the org's filtered lists) -->
        <div v-if="stats" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <router-link
                v-for="card in totalsCards" :key="card.key" :to="listLink(card.route)"
                class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 transition hover:ring-brand-300 dark:hover:ring-brand-700"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ stats.totals[card.key] }}</p>
                <p v-if="card.key === 'certificates' && stats.totals.deleted" class="mt-1 text-xs text-slate-400">{{ stats.totals.deleted }} deleted</p>
                <p class="mt-2 text-xs font-medium text-brand-600 dark:text-brand-400">View {{ card.label.toLowerCase() }} →</p>
            </router-link>
        </div>

        <!-- By status -->
        <div v-if="stats" class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-slate-100">Credentials by status</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <router-link
                    v-for="status in statusOrder" :key="status" :to="listLink('admin.certificates')"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                >
                    <StatusBadge :status="status" />
                    <span class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ stats.by_status[status] || 0 }}</span>
                </router-link>
            </div>
        </div>

        <!-- Expiring soon -->
        <div v-if="stats" class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-slate-100">Expiring within 30 days</h2>
            <p v-if="!stats.expiring_soon.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">Nothing expiring soon. 🎉</p>
            <ul v-else class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="certificate in stats.expiring_soon" :key="certificate.uuid" class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">{{ certificate.certificate_number }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ certificate.recipient?.full_name }} · {{ certificate.template?.name }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-medium text-orange-600 dark:text-orange-400">{{ certificate.expiry_date?.slice(0, 10) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
