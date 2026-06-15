<script setup>
import { onMounted, ref } from 'vue';
import http from '@/api/http';

const notes = ref([]);
const currentVersion = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await http.get('/changelog');
        currentVersion.value = data.current_version;
        notes.value = data.notes;
    } finally {
        loading.value = false;
    }
});

function lines(body) {
    return (body || '').split(/\r?\n/).map((l) => l.replace(/^\s*[-•*]\s?/, '').trim()).filter(Boolean);
}

function formatDate(value) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 py-12 sm:py-16">
        <header class="text-center">
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 sm:text-4xl">Changelog</h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400">
                Everything new on the platform.
                <span v-if="currentVersion"> Currently on <span class="font-semibold text-brand-600 dark:text-brand-400">v{{ currentVersion }}</span>.</span>
            </p>
        </header>

        <div v-if="loading" class="mt-12 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>

        <div v-else-if="!notes.length" class="mt-12 rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center dark:border-slate-800">
            <p class="font-medium text-slate-900 dark:text-slate-100">No releases yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Check back soon.</p>
        </div>

        <!-- Timeline -->
        <ol v-else class="mt-12 space-y-10 border-l-2 border-slate-200 pl-6 dark:border-slate-800">
            <li v-for="note in notes" :key="note.uuid" class="relative">
                <span class="absolute -left-[31px] top-1 flex h-4 w-4 items-center justify-center rounded-full bg-brand-600 ring-4 ring-white dark:ring-slate-950" />
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="rounded-md bg-brand-600 px-2 py-0.5 text-sm font-semibold text-white">v{{ note.version }}</span>
                    <h2 v-if="note.title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ note.title }}</h2>
                </div>
                <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">{{ formatDate(note.released_on) }}</p>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li v-for="(line, i) in lines(note.body)" :key="i" class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400" />
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </li>
        </ol>
    </div>
</template>
