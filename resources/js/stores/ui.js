import { defineStore } from 'pinia';

let nextId = 1;

export const useUiStore = defineStore('ui', {
    state: () => ({
        toasts: [],
        sidebarOpen: false,
    }),

    actions: {
        toast(message, type = 'success', timeout = 4000) {
            const id = nextId++;
            this.toasts.push({ id, message, type });
            setTimeout(() => this.dismiss(id), timeout);
        },
        success(message) {
            this.toast(message, 'success');
        },
        error(message) {
            this.toast(message, 'error', 6000);
        },
        info(message) {
            this.toast(message, 'info');
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    },
});
