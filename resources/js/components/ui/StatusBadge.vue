<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const palette = {
    draft: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
    ready: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300',
    archived: 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
    pending: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300',
    queued: 'bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-300 dark:text-sky-300',
    sent: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300',
    failed: 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300',
    revoked: 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300',
    expired: 'bg-orange-100 dark:bg-orange-900/40 text-orange-800 dark:text-orange-300',
    renewed: 'bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-300',
    cancelled: 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400',
    deleted: 'bg-slate-300 dark:bg-slate-600 text-slate-700 dark:text-slate-300',
};

const classes = computed(() => palette[props.status] || 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300');
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="classes">
        {{ status }}
    </span>
</template>
