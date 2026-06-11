import { defineStore } from 'pinia';

const media = window.matchMedia('(prefers-color-scheme: dark)');

export const useThemeStore = defineStore('theme', {
    state: () => ({
        // 'light' | 'dark' | 'system'
        mode: localStorage.getItem('theme') || 'system',
    }),

    getters: {
        isDark: (state) => state.mode === 'dark' || (state.mode === 'system' && media.matches),
    },

    actions: {
        init() {
            this.apply();
            media.addEventListener('change', () => {
                if (this.mode === 'system') this.apply();
            });
        },

        setMode(mode) {
            this.mode = mode;
            localStorage.setItem('theme', mode);
            this.apply();
        },

        cycle() {
            const order = ['light', 'dark', 'system'];
            this.setMode(order[(order.indexOf(this.mode) + 1) % order.length]);
        },

        apply() {
            document.documentElement.classList.toggle('dark', this.isDark);
        },
    },
});
