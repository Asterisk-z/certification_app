import { reactive } from 'vue';

// Drives the top navigation progress bar. NProgress-style trickle: jump in,
// creep toward 90% while the route chunk/data loads, then snap to 100% and
// fade out.
export const progress = reactive({
    active: false,
    value: 0,
});

let trickleTimer = null;
let fadeTimer = null;

export function startProgress() {
    clearTimeout(fadeTimer);
    clearInterval(trickleTimer);

    progress.active = true;
    progress.value = 12;

    trickleTimer = setInterval(() => {
        // Decreasing steps so it slows as it approaches 90%.
        const remaining = 90 - progress.value;
        if (remaining > 0) {
            progress.value += Math.max(0.5, remaining * 0.12);
        }
    }, 180);
}

export function doneProgress() {
    clearInterval(trickleTimer);
    progress.value = 100;

    fadeTimer = setTimeout(() => {
        progress.active = false;
        progress.value = 0;
    }, 250);
}
