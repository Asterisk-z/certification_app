<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '', remember: false });
const errors = ref({});
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    try {
        const user = await auth.login(form);
        const redirect = route.query.redirect;
        if (redirect) {
            router.push(redirect);
        } else {
            const home = { admin: 'admin.dashboard', organization: 'org.dashboard', recipient: 'portal.dashboard' };
            router.push({ name: home[user.role] || 'login' });
        }
    } catch (e) {
        errors.value = e.response?.data?.errors || { email: [e.response?.data?.message || 'Login failed.'] };
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Sign in</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Access your credentials dashboard.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="errors.email" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.email[0] }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="errors.password" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.password[0] }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500" />
                    Remember me
                </label>
                <router-link :to="{ name: 'forgot-password' }" class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
                    Forgot password?
                </router-link>
            </div>

            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            >
                {{ loading ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>
    </div>
</template>
