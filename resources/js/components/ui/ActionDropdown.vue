<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    // [{ key, label, danger }]
    actions: { type: Array, required: true },
});

const emit = defineEmits(['action']);

const open = ref(false);
const root = ref(null);

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));

function pick(action) {
    open.value = false;
    emit('action', action.key);
}
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
            @click.stop="open = !open"
        >
            Actions ▾
        </button>
        <div
            v-if="open"
            class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-xl bg-white dark:bg-slate-900 py-1 shadow-lg ring-1 ring-slate-200 dark:ring-slate-800"
        >
            <button
                v-for="action in actions"
                :key="action.key"
                class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800/60"
                :class="action.danger ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300'"
                @click="pick(action)"
            >
                {{ action.label }}
            </button>
        </div>
    </div>
</template>
