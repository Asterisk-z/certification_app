<script setup>
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();

const styles = {
    success: 'bg-emerald-600',
    error: 'bg-rose-600',
    info: 'bg-slate-800',
};
</script>

<template>
    <div class="fixed inset-x-0 bottom-4 z-[100] flex flex-col items-center gap-2 px-4 sm:items-end sm:right-4 sm:inset-x-auto">
        <transition-group name="toast">
            <div
                v-for="toast in ui.toasts"
                :key="toast.id"
                class="flex w-full max-w-sm items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
                :class="styles[toast.type] || styles.info"
                role="alert"
            >
                <span class="flex-1">{{ toast.message }}</span>
                <button class="text-white/70 hover:text-white" @click="ui.dismiss(toast.id)" aria-label="Dismiss">✕</button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
