<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { ensureCsrf } from '@/api/http';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();

const form = reactive({
    token: route.query.token || '',
    email: route.query.email || '',
    password: '',
    password_confirmation: '',
});
const errors = ref({});
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    try {
        await ensureCsrf();
        await http.post('/auth/reset-password', form);
        ui.success('Password reset. You can now sign in.');
        router.push({ name: 'login' });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (e.response?.data?.message && !Object.keys(errors.value).length) {
            ui.error(e.response.data.message);
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Reset password</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose a new password for {{ form.email }}.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">New password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="errors.password" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.password[0] }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
            </div>
            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            >
                {{ loading ? 'Resetting…' : 'Reset password' }}
            </button>
        </form>
    </div>
</template>
