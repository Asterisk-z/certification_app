<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search…' },
    delay: { type: Number, default: 300 },
});

const emit = defineEmits(['update:modelValue', 'search']);

const local = ref(props.modelValue);
let timer = null;

watch(local, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        emit('update:modelValue', value);
        emit('search', value);
    }, props.delay);
});

watch(
    () => props.modelValue,
    (value) => {
        if (value !== local.value) local.value = value;
    }
);
</script>

<template>
    <div class="relative w-full sm:max-w-xs">
        <svg
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
        </svg>
        <input
            v-model="local"
            type="search"
            :placeholder="placeholder"
            class="block w-full rounded-lg border-slate-300 dark:border-slate-700 pl-9 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
        />
    </div>
</template>
