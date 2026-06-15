<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '@/api/http';

const router = useRouter();

const props = defineProps({
    // 'light' for dark backgrounds (admin sidebar), 'default' otherwise.
    variant: { type: String, default: 'default' },
});

const version = ref(null);
const notes = ref([]);
const open = ref(false);

onMounted(async () => {
    try {
        const { data } = await http.get('/changelog');
        version.value = data.current_version;
        notes.value = data.notes;
    } catch {
        // No changelog yet — the badge stays hidden.
    }
});

// Turn the body into clean bullet lines (strip a leading "-" or "•").
function lines(body) {
    return (body || '')
        .split(/\r?\n/)
        .map((l) => l.replace(/^\s*[-•*]\s?/, '').trim())
        .filter(Boolean);
}

function formatDate(value) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function viewFullPage() {
    open.value = false;
    router.push({ name: 'changelog' });
}
</script>

<template>
    <button
        v-if="version"
        type="button"
        class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
        :class="variant === 'light'
            ? 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
        title="View changelog"
        @click="open = true"
    >
        v{{ version }}
    </button>

    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[120] flex items-end justify-center p-4 sm:items-center">
            <div class="fixed inset-0 bg-slate-900/50" @click="open = false" />
            <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">What's new</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Currently on v{{ version }}</p>
                    </div>
                    <button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800" aria-label="Close" @click="open = false">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                    <p v-if="!notes.length" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        No release notes yet.
                    </p>
                    <div v-for="note in notes" :key="note.uuid">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="rounded-md bg-brand-600 px-2 py-0.5 text-sm font-semibold text-white">v{{ note.version }}</span>
                            <span v-if="note.title" class="font-medium text-slate-900 dark:text-slate-100">{{ note.title }}</span>
                            <span class="text-xs text-slate-400">{{ formatDate(note.released_on) }}</span>
                        </div>
                        <ul class="mt-2 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                            <li v-for="(line, i) in lines(note.body)" :key="i" class="flex gap-2">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400" />
                                <span>{{ line }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-if="notes.length" class="border-t border-slate-100 px-6 py-3 text-center dark:border-slate-800">
                    <button class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" @click="viewFullPage">
                        View full changelog →
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>
