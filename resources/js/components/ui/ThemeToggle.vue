<script setup>
import { computed } from 'vue';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

const labels = { light: 'Light theme', dark: 'Dark theme', system: 'System theme' };
const title = computed(() => `${labels[theme.mode]} — click to switch`);
</script>

<template>
    <button
        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        :title="title"
        :aria-label="title"
        @click="theme.cycle()"
    >
        <!-- Sun -->
        <svg v-if="theme.mode === 'light'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m14.95 6.95l-1.06-1.06M7.11 7.11L6.05 6.05m11.9 0l-1.06 1.06M7.11 16.89l-1.06 1.06M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
        </svg>
        <!-- Moon -->
        <svg v-else-if="theme.mode === 'dark'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 14.85A9.72 9.72 0 0112 21.75 9.75 9.75 0 019.15 2.69a.38.38 0 01.48.48 7.88 7.88 0 0011.64 11.2.38.38 0 01.48.48z" />
        </svg>
        <!-- Monitor (system) -->
        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z" />
        </svg>
    </button>
</template>
