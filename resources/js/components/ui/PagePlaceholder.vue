<script setup>
defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-16 text-center">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This section is under construction.</p>
    </div>
</template>
