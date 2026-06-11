<script setup>
import { reactive, ref } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const form = reactive({
    name: auth.user?.name || '',
    current_password: '',
    password: '',
    password_confirmation: '',
});
const errors = ref({});
const saving = ref(false);

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        await ensureCsrf();
        const { data } = await http.put('/me/profile', {
            name: form.name,
            ...(form.password
                ? {
                      current_password: form.current_password,
                      password: form.password,
                      password_confirmation: form.password_confirmation,
                  }
                : {}),
        });
        auth.user = data.user;
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';
        ui.success('Profile updated.');
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) ui.error('Could not update your profile.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My profile</h1>

        <form class="mt-6 space-y-5 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800" @submit.prevent="save">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Full name</label>
                <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="errors.name" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.name[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input :value="auth.user?.email" type="email" disabled class="mt-1 block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400" />
                <p class="mt-1 text-xs text-slate-400">Your email is managed by the issuing administrator.</p>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Change password</h2>
                <div class="mt-3 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Current password</label>
                        <input v-model="form.current_password" type="password" autocomplete="current-password" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        <p v-if="errors.current_password" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.current_password[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">New password</label>
                        <input v-model="form.password" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        <p v-if="errors.password" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.password[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm new password</label>
                        <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 pt-4">
                <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ saving ? 'Saving…' : 'Save changes' }}
                </button>
            </div>
        </form>
    </div>
</template>
