<script setup>
import { computed, ref, watch } from 'vue';
import http, { ensureCsrf } from '@/api/http';
import { useUiStore } from '@/stores/ui';

const props = defineProps({
    open: { type: Boolean, default: false },
    // When opened from a group page, everyone is added to this group.
    fixedGroup: { type: Object, default: null },
    groups: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'done']);

const ui = useUiStore();

const mode = ref('paste'); // paste | file
const text = ref('');
const file = ref(null);
const fileInput = ref(null);
const groupUuids = ref([]);
const submitting = ref(false);
const result = ref(null);
const errorMessage = ref('');

const canSubmit = computed(() =>
    mode.value === 'paste' ? text.value.trim().length > 0 : !!file.value
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            mode.value = 'paste';
            text.value = '';
            file.value = null;
            groupUuids.value = props.fixedGroup ? [props.fixedGroup.uuid] : [];
            result.value = null;
            errorMessage.value = '';
        }
    }
);

async function submit() {
    submitting.value = true;
    errorMessage.value = '';
    result.value = null;
    try {
        await ensureCsrf();
        const fd = new FormData();
        if (mode.value === 'file') {
            fd.append('file', file.value);
        } else {
            fd.append('text', text.value);
        }
        groupUuids.value.forEach((uuid) => fd.append('group_uuids[]', uuid));

        const { data } = await http.post('/admin/recipients/bulk', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        result.value = data;
        ui.success(data.message);
        emit('done');
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Bulk add failed.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="fixed inset-0 bg-slate-900/50" @click="$emit('close')" />
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Bulk add recipients{{ fixedGroup ? ` to “${fixedGroup.name}”` : '' }}
                </h2>

                <!-- Mode tabs -->
                <div class="mt-4 flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        v-for="tab in [
                            { key: 'paste', label: 'Paste list' },
                            { key: 'file', label: 'Upload Excel/CSV' },
                        ]"
                        :key="tab.key"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="mode === tab.key
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        @click="mode = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div v-if="mode === 'paste'">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            One recipient per line
                        </label>
                        <p class="text-xs text-slate-400">Format: Name, email, phone (phone optional — commas, semicolons or tabs all work).</p>
                        <textarea
                            v-model="text"
                            rows="8"
                            placeholder="Jane Doe, jane@example.com, +44 7700 900000&#10;John Smith, john@example.com"
                            class="mt-2 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700"
                        />
                    </div>

                    <div v-else>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Excel or CSV file
                        </label>
                        <p class="text-xs text-slate-400">
                            Columns: <code class="font-mono">full_name</code>, <code class="font-mono">email</code>,
                            <code class="font-mono">phone</code> (optional) —
                            <a href="/api/admin/recipients/bulk-format" class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" target="_blank">download the format</a>.
                        </p>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="mt-2 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-900/40 dark:file:text-brand-300"
                            @change="file = $event.target.files[0]"
                        />
                    </div>

                    <div v-if="!fixedGroup && groups.length">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Add everyone to groups (optional)</label>
                        <div class="mt-2 max-h-32 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                            <label
                                v-for="group in groups"
                                :key="group.uuid"
                                class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60"
                            >
                                <input
                                    v-model="groupUuids"
                                    type="checkbox"
                                    :value="group.uuid"
                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
                                />
                                {{ group.name }}
                            </label>
                        </div>
                    </div>

                    <div v-if="errorMessage" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                        {{ errorMessage }}
                    </div>

                    <div v-if="result" class="space-y-2">
                        <div class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                            {{ result.created }} added &amp; invited, {{ result.updated }} updated.
                        </div>
                        <div v-if="result.failures?.length" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/50 dark:text-amber-300">
                            <p class="font-semibold">{{ result.failures.length }} row(s) skipped:</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs">
                                <li v-for="failure in result.failures" :key="failure.row">
                                    Row {{ failure.row }}: {{ failure.errors.join('; ') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:justify-end">
                    <button
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60"
                        @click="$emit('close')"
                    >
                        {{ result ? 'Close' : 'Cancel' }}
                    </button>
                    <button
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                        :disabled="!canSubmit || submitting"
                        @click="submit"
                    >
                        {{ submitting ? 'Adding…' : 'Add recipients' }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>
