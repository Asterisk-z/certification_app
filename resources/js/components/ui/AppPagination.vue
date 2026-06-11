<script setup>
defineProps({
    meta: { type: Object, default: null },
});

defineEmits(['change']);
</script>

<template>
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between gap-3 pt-4 text-sm">
        <p class="text-slate-500 dark:text-slate-400">
            Page {{ meta.current_page }} of {{ meta.last_page }}
            <span class="hidden sm:inline">({{ meta.total }} results)</span>
        </p>
        <div class="flex gap-2">
            <button
                class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium hover:bg-slate-50 dark:hover:bg-slate-800/60 disabled:opacity-40"
                :disabled="meta.current_page <= 1"
                @click="$emit('change', meta.current_page - 1)"
            >
                Previous
            </button>
            <button
                class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-medium hover:bg-slate-50 dark:hover:bg-slate-800/60 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="$emit('change', meta.current_page + 1)"
            >
                Next
            </button>
        </div>
    </div>
</template>
