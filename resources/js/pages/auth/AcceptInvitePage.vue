<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ensureCsrf } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

// The full signed URL is passed through as query params; rebuild it so the
// signature validates server-side.
const signedQuery = new URLSearchParams(route.query).toString();
const baseUrl = `/api/auth/invite/${route.params.uuid}?${signedQuery}`;

const recipient = ref(null);
const invalid = ref(false);
const loading = ref(false);
const form = reactive({ password: '', password_confirmation: '' });
const errors = ref({});

onMounted(async () => {
    try {
        const { data } = await axios.get(baseUrl, { headers: { Accept: 'application/json' } });
        recipient.value = data.recipient;
    } catch {
        invalid.value = true;
    }
});

async function submit() {
    loading.value = true;
    errors.value = {};
    try {
        await ensureCsrf();
        const { data } = await axios.post(baseUrl, form, {
            headers: { Accept: 'application/json' },
            withCredentials: true,
            withXSRFToken: true,
        });
        auth.user = data.user;
        auth.hydrated = true;
        ui.success('Welcome! Your account is ready.');
        router.push({ name: 'portal.dashboard' });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            ui.error(e.response?.data?.message || 'Could not accept the invite.');
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <div v-if="invalid" class="rounded-lg bg-rose-50 dark:bg-rose-950/50 p-4 text-sm text-rose-800 dark:text-rose-300">
            This invite link is invalid or has expired. Please contact your administrator.
        </div>

        <template v-else-if="recipient">
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Welcome, {{ recipient.full_name }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Create a password for <strong>{{ recipient.email }}</strong> to access your credentials.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
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
                    {{ loading ? 'Creating account…' : 'Create account' }}
                </button>
            </form>
        </template>

        <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Checking your invite…</p>
    </div>
</template>
