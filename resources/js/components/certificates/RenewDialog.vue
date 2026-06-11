<script setup>
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    certificate: { type: Object, default: null },
});

const emit = defineEmits(['close', 'confirm']);

const form = reactive({ issue_date: '', expiry_date: '' });
const error = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.issue_date = new Date().toISOString().slice(0, 10);
            form.expiry_date = '';
            error.value = '';
        }
    }
);

function submit() {
    if (form.expiry_date && form.expiry_date <= form.issue_date) {
        error.value = 'The valid-until date must be after the issue date.';
        return;
    }
    emit('confirm', {
        issue_date: form.issue_date,
        ...(form.expiry_date ? { expiry_date: form.expiry_date } : {}),
    });
}
</script>

<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="fixed inset-0 bg-slate-900/50" @click="$emit('close')" />
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Renew {{ certificate?.certificate_number }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    A new credential is issued with the same details and a new number; this one is marked renewed.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Issue date</label>
                        <input
                            v-model="form.issue_date"
                            type="date"
                            required
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Valid until</label>
                        <input
                            v-model="form.expiry_date"
                            type="date"
                            :min="form.issue_date"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700"
                        />
                        <p class="mt-1 text-xs text-slate-400">
                            Leave empty to use the template's standard validity period.
                        </p>
                        <p v-if="error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ error }}</p>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                            @click="$emit('close')"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                        >
                            Renew credential
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </teleport>
</template>
