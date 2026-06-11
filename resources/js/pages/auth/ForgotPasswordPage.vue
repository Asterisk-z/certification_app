<script setup>
import { ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const email = ref('');
const loading = ref(false);
const sent = ref(false);

async function submit() {
    loading.value = true;
    try {
        await ensureCsrf();
        await http.post('/auth/forgot-password', { email: email.value });
        sent.value = true;
    } catch (e) {
        ui.error(e.response?.data?.message || 'Something went wrong.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Forgot password</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">We'll email you a link to reset it.</p>

        <div v-if="sent" class="mt-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-4 text-sm text-emerald-800 dark:text-emerald-300">
            If that email exists, a reset link has been sent. Check your inbox.
        </div>

        <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    required
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
            </div>
            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            >
                {{ loading ? 'Sending…' : 'Send reset link' }}
            </button>
        </form>

        <router-link :to="{ name: 'login' }" class="mt-4 block text-center text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
            Back to sign in
        </router-link>
    </div>
</template>
