<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import NotificationsBell from '@/components/ui/NotificationsBell.vue';
import ThemeToggle from '@/components/ui/ThemeToggle.vue';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-950">
        <header class="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6">
                <router-link :to="{ name: 'portal.dashboard' }" class="flex items-center">
                    <img src="/images/logo.png" alt="HSE Board" class="h-9 w-auto rounded-md bg-white px-1.5 py-1" />
                </router-link>
                <nav class="flex items-center gap-1 text-sm font-medium sm:gap-3">
                    <ThemeToggle />
                    <NotificationsBell />
                    <router-link :to="{ name: 'portal.dashboard' }"
                        class="rounded-lg px-3 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        active-class="text-brand-700 dark:text-brand-300">
                        Credentials
                    </router-link>
                    <router-link :to="{ name: 'portal.profile' }"
                        class="rounded-lg px-3 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        active-class="text-brand-700 dark:text-brand-300">
                        Profile
                    </router-link>
                    <button
                        class="rounded-lg bg-slate-100 dark:bg-slate-800 px-3 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700"
                        @click="logout">
                        Sign out
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
            <router-view />
        </main>
    </div>
</template>
