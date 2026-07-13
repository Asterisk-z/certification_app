<script setup>
import { useAuthStore } from '@/stores/auth';
import ThemeToggle from '@/components/ui/ThemeToggle.vue';
import VersionBadge from '@/components/ui/VersionBadge.vue';

const auth = useAuthStore();
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
                <router-link to="/" class="flex items-center">
                    <img src="/images/logo.png" alt="HSE Board" class="h-9 w-auto rounded-md bg-white px-1.5 py-1" />
                </router-link>
                <nav class="flex items-center gap-3 text-sm font-medium">
                    <ThemeToggle />
                    <template v-if="auth.isAuthenticated">
                        <router-link :to="auth.isAdmin ? { name: 'admin.dashboard' } : { name: 'portal.dashboard' }"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-white hover:bg-brand-700">
                            Dashboard
                            {{ auth.isAdmin ? 'Admin' : 'Portal' }}
                        </router-link>
                    </template>
                    <router-link v-else :to="{ name: 'login' }"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-white hover:bg-brand-700">
                        Sign in
                    </router-link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <router-view />
        </main>

        <footer
            class="flex flex-col items-center justify-center gap-1 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
            <span>© {{ new Date().getFullYear() }} HSE Board. All rights reserved.</span>
            <VersionBadge />
        </footer>
    </div>
</template>
