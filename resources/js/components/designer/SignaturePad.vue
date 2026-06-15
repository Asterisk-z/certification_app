<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    penColor: { type: String, default: '#111827' },
});

const canvas = ref(null);
const hasDrawn = ref(false);

let ctx = null;
let drawing = false;
let last = null;

// Size the bitmap to the displayed box × devicePixelRatio so strokes stay
// crisp. Called once the element is laid out (it lives inside a modal, so it
// has a real size by the time onMounted fires).
function setup() {
    const el = canvas.value;
    const rect = el.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    el.width = Math.round(rect.width * dpr);
    el.height = Math.round(rect.height * dpr);
    ctx = el.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = props.penColor;
}

onMounted(setup);

function point(event) {
    const rect = canvas.value.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

function start(event) {
    drawing = true;
    last = point(event);
    canvas.value.setPointerCapture(event.pointerId);
}

function move(event) {
    if (!drawing) return;
    const p = point(event);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    hasDrawn.value = true;
}

function end() {
    drawing = false;
}

function clear() {
    const el = canvas.value;
    const dpr = window.devicePixelRatio || 1;
    ctx.clearRect(0, 0, el.width / dpr, el.height / dpr);
    hasDrawn.value = false;
}

// Resolve to a transparent PNG Blob of the strokes, or null if nothing was
// drawn — so the caller can refuse to save an empty signature.
function toBlob() {
    return new Promise((resolve) => {
        if (!hasDrawn.value) return resolve(null);
        canvas.value.toBlob((blob) => resolve(blob), 'image/png');
    });
}

defineExpose({ clear, toBlob, hasDrawn });
</script>

<template>
    <div>
        <canvas
            ref="canvas"
            class="h-40 w-full cursor-crosshair touch-none rounded-lg border border-slate-300 bg-white dark:border-slate-700"
            @pointerdown="start"
            @pointermove="move"
            @pointerup="end"
            @pointerleave="end"
        />
        <div class="mt-2 flex items-center justify-between">
            <p class="text-xs text-slate-500 dark:text-slate-400">Draw the signature above using your mouse or finger.</p>
            <button
                type="button"
                class="text-xs font-medium text-slate-600 hover:text-rose-600 dark:text-slate-400"
                @click="clear"
            >
                Clear
            </button>
        </div>
    </div>
</template>
