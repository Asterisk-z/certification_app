<script setup>
import { onMounted, ref } from 'vue';
import http from '@/api/http';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const stats = ref(null);

const statusOrder = ['pending', 'queued', 'sent', 'failed', 'revoked', 'expired', 'renewed', 'cancelled'];

onMounted(async () => {
    const { data } = await http.get('/admin/dashboard/stats');
    stats.value = data;
});

function timeAgo(value) {
    const seconds = Math.floor((Date.now() - new Date(value)) / 1000);
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}
</script>

<template>
    <div v-if="stats">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A live overview of your credential platform.</p>

        <!-- Totals -->
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <div
                v-for="(label, key) in { certificates: 'Credentials', recipients: 'Recipients', templates: 'Templates', groups: 'Groups', deleted: 'Deleted' }"
                :key="key"
                class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ label }}</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ stats.totals[key] }}</p>
            </div>
        </div>

        <!-- By status -->
        <div class="mt-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-slate-100">Credentials by status</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <router-link
                    v-for="status in statusOrder"
                    :key="status"
                    :to="{ name: 'admin.certificates' }"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                >
                    <StatusBadge :status="status" />
                    <span class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ stats.by_status[status] || 0 }}</span>
                </router-link>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Expiring soon -->
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
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

            <!-- Recent activity (admin only — the activity log isn't tenant-scoped) -->
            <div v-if="auth.isAdmin" class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">Recent activity</h2>
                    <router-link :to="{ name: 'admin.logs.activity' }" class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
                        View all
                    </router-link>
                </div>
                <p v-if="!stats.recent_activity.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">No activity yet.</p>
                <ul v-else class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="entry in stats.recent_activity" :key="entry.id" class="py-2.5">
                        <p class="text-sm text-slate-900 dark:text-slate-100">
                            <span class="font-medium">{{ entry.causer || 'System' }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> — {{ entry.description.replaceAll('_', ' ') }}</span>
                        </p>
                        <p class="text-xs text-slate-400">{{ timeAgo(entry.created_at) }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div v-else class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">Loading dashboard…</div>
</template>
