<script setup>
import { computed, nextTick, ref } from 'vue';
import { onClickOutside } from '@vueuse/core';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    // [{ value, label, sub? }]
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select…' },
    // When set, a "create" row appears for a typed term with no exact match.
    createLabel: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'create']);

const root = ref(null);
const input = ref(null);
const open = ref(false);
const query = ref('');

const selectedLabel = computed(() => props.options.find((o) => o.value === props.modelValue)?.label ?? '');

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();
    if (!term) return props.options;
    return props.options.filter(
        (o) => o.label.toLowerCase().includes(term) || (o.sub || '').toLowerCase().includes(term)
    );
});

const showCreate = computed(() => {
    const term = query.value.trim();
    return (
        props.createLabel &&
        term.length > 0 &&
        !props.options.some((o) => o.label.toLowerCase() === term.toLowerCase())
    );
});

function openMenu() {
    if (props.disabled) return;
    open.value = true;
    query.value = '';
    nextTick(() => input.value?.focus());
}

function choose(option) {
    emit('update:modelValue', option.value);
    open.value = false;
    query.value = '';
}

function create() {
    emit('create', query.value.trim());
    open.value = false;
    query.value = '';
}

onClickOutside(root, () => {
    open.value = false;
    query.value = '';
});
</script>

<template>
    <div ref="root" class="relative">
        <!-- Closed state: a button showing the current selection -->
        <button
            v-if="!open"
            type="button"
            :disabled="disabled"
            class="flex w-full items-center justify-between rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-left text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:opacity-50"
            @click="openMenu"
        >
            <span :class="selectedLabel ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500'">
                {{ selectedLabel || placeholder }}
            </span>
            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
        </button>

        <!-- Open state: a search input + the option list -->
        <div v-else>
            <input
                ref="input"
                v-model="query"
                type="text"
                :placeholder="`Search…`"
                class="block w-full rounded-lg border-brand-400 dark:border-brand-600 bg-white dark:bg-slate-900 px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                @keydown.esc="open = false"
            />
            <ul class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 py-1 text-sm shadow-lg">
                <li
                    v-for="option in filtered"
                    :key="option.value || '_empty'"
                    class="cursor-pointer px-3 py-2 hover:bg-brand-50 dark:hover:bg-brand-900/30"
                    :class="option.value === modelValue ? 'bg-brand-50/60 dark:bg-brand-900/20' : ''"
                    @click="choose(option)"
                >
                    <span class="text-slate-900 dark:text-slate-100">{{ option.label }}</span>
                    <span v-if="option.sub" class="ml-1 text-xs text-slate-500 dark:text-slate-400">{{ option.sub }}</span>
                </li>
                <li v-if="!filtered.length && !showCreate" class="px-3 py-2 text-slate-400 dark:text-slate-500">No matches</li>
                <li
                    v-if="showCreate"
                    class="cursor-pointer border-t border-slate-100 dark:border-slate-800 px-3 py-2 font-medium text-brand-700 dark:text-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/30"
                    @click="create"
                >
                    + {{ createLabel }} “{{ query.trim() }}”
                </li>
            </ul>
        </div>
    </div>
</template>
