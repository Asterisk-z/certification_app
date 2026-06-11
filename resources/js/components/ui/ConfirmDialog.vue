<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: 'This action cannot be undone.' },
    confirmLabel: { type: String, default: 'Confirm' },
    danger: { type: Boolean, default: true },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="fixed inset-0 bg-slate-900/50" @click="$emit('cancel')" />
            <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ message }}</p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        @click="$emit('cancel')"
                    >
                        Cancel
                    </button>
                    <button
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white"
                        :class="danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-brand-600 hover:bg-brand-700'"
                        @click="$emit('confirm')"
                    >
                        {{ confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>
