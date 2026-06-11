<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    // [{ key, label, danger }]
    actions: { type: Array, required: true },
});

const emit = defineEmits(['action']);

const open = ref(false);
const root = ref(null);
const menu = ref(null);
const menuStyle = ref({});

const MENU_WIDTH = 176; // w-44

function toggle() {
    if (open.value) {
        open.value = false;
        return;
    }

    // The menu teleports to <body> with fixed positioning so it can never be
    // clipped by table overflow. Flip upwards when near the viewport bottom.
    const rect = root.value.getBoundingClientRect();
    const estimatedHeight = props.actions.length * 36 + 10;
    const openUp = window.innerHeight - rect.bottom < estimatedHeight + 12;

    menuStyle.value = {
        position: 'fixed',
        left: `${Math.max(8, rect.right - MENU_WIDTH)}px`,
        top: openUp ? 'auto' : `${rect.bottom + 4}px`,
        bottom: openUp ? `${window.innerHeight - rect.top + 4}px` : 'auto',
        width: `${MENU_WIDTH}px`,
    };
    open.value = true;
}

function onClickOutside(event) {
    if (
        open.value &&
        !root.value?.contains(event.target) &&
        !menu.value?.contains(event.target)
    ) {
        open.value = false;
    }
}

function close() {
    open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    // Reposition is overkill — just close if the page scrolls or resizes.
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
});

function pick(action) {
    open.value = false;
    emit('action', action.key);
}
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
            @click="toggle"
        >
            Actions ▾
        </button>
        <teleport to="body">
            <div
                v-if="open"
                ref="menu"
                :style="menuStyle"
                class="z-[90] overflow-hidden rounded-xl bg-white dark:bg-slate-800 py-1 shadow-lg ring-1 ring-slate-200 dark:ring-slate-700"
            >
                <button
                    v-for="action in actions"
                    :key="action.key"
                    class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-700"
                    :class="action.danger ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-200'"
                    @click="pick(action)"
                >
                    {{ action.label }}
                </button>
            </div>
        </teleport>
    </div>
</template>
