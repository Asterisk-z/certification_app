<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';

const open = ref(false);
const notifications = ref([]);
const unread = ref(0);
const root = ref(null);
let timer = null;

async function load() {
    try {
        const { data } = await http.get('/notifications');
        notifications.value = data.notifications;
        unread.value = data.unread_count;
    } catch {
        // Silently ignore polling errors (e.g. logged out).
    }
}

async function markAllRead() {
    await ensureCsrf();
    await http.post('/notifications/read-all');
    load();
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false;
}

onMounted(() => {
    load();
    timer = setInterval(load, 60000);
    document.addEventListener('click', onClickOutside);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('click', onClickOutside);
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
    <div ref="root" class="relative">
        <button
            class="relative rounded-lg p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200"
            aria-label="Notifications"
            @click.stop="open = !open"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span
                v-if="unread"
                class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-xs font-bold text-white"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-slate-200 dark:ring-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-4 py-3">
                <p class="font-semibold text-slate-900 dark:text-slate-100">Notifications</p>
                <button v-if="unread" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300" @click="markAllRead">
                    Mark all read
                </button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <p v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    Nothing here yet.
                </p>
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="border-b border-slate-50 dark:border-slate-800/60 px-4 py-3"
                    :class="!notification.read_at && 'bg-brand-50/50'"
                >
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ notification.data.title }}</p>
                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ notification.data.message }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ timeAgo(notification.created_at) }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
