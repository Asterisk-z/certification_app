<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http, { ensureCsrf } from '@/api/http';
import { useUiStore } from '@/stores/ui';

const router = useRouter();
const ui = useUiStore();

const form = reactive({ subject: '', body: '', audience: 'all' });
const errors = ref({});
const sending = ref(false);

async function send() {
    sending.value = true;
    errors.value = {};
    try {
        await ensureCsrf();
        const { data } = await http.post('/admin/newsletters', form);
        ui.success(data.message);
        router.push({ name: 'admin.newsletters' });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) ui.error('Could not queue the newsletter.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Compose newsletter</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Send an update to all recipients, admins, or everyone.</p>

        <form class="mt-6 space-y-5 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800" @submit.prevent="send">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Audience</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="option in [
                            { value: 'all', label: 'Everyone' },
                            { value: 'recipients', label: 'Certified users' },
                            { value: 'admins', label: 'Admins' },
                        ]"
                        :key="option.value"
                        type="button"
                        class="rounded-lg border px-4 py-2 text-sm font-medium"
                        :class="form.audience === option.value ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        @click="form.audience = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Subject</label>
                <input
                    v-model="form.subject" type="text" required
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="errors.subject" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.subject[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Message</label>
                <textarea
                    v-model="form.body" rows="10" required
                    placeholder="Write your update… plain text, line breaks are preserved."
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="errors.body" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.body[0] }}</p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row sm:justify-end">
                <router-link :to="{ name: 'admin.newsletters' }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    Cancel
                </router-link>
                <button type="submit" :disabled="sending" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ sending ? 'Queuing…' : 'Send newsletter' }}
                </button>
            </div>
        </form>
    </div>
</template>
