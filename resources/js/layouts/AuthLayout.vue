<script setup>
import ThemeToggle from '@/components/ui/ThemeToggle.vue';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-12 dark:bg-slate-950">
        <div class="absolute right-4 top-4">
            <ThemeToggle />
        </div>
        <router-link to="/" class="mb-8">
            <img src="/images/logo.png" alt="HSE Board" class="h-14 w-auto rounded-lg bg-white px-2 py-1.5" />
        </router-link>
        <div
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800 sm:p-8">
            <router-view />
        </div>
    </div>
</template>
