<script setup>
import { computed } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
    scale: { type: Number, required: true },
    bgWidth: { type: Number, required: true },
    bgHeight: { type: Number, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'update']);

const displayText = computed(() =>
    props.block.is_dynamic ? '{{' + props.block.slug + '}}' : props.block.value
);

const width = computed(() => props.block.width ?? 300);
// Unsized text blocks grow with their font so large type is never clipped.
const height = computed(() =>
    props.block.height ?? (props.block.type === 'text' ? Math.max(40, (props.block.font_size || 16) * 1.4) : 120)
);

const style = computed(() => ({
    left: `${props.block.pos_x * props.scale}px`,
    top: `${props.block.pos_y * props.scale}px`,
    width: `${width.value * props.scale}px`,
    height: `${height.value * props.scale}px`,
    fontFamily: props.block.font_family,
    fontSize: `${props.block.font_size * props.scale}px`,
    color: props.block.font_color,
    fontWeight: props.block.font_weight,
    textAlign: props.block.text_align,
}));

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function startDrag(event) {
    emit('select');
    event.preventDefault();
    event.target.setPointerCapture(event.pointerId);

    const startX = event.clientX;
    const startY = event.clientY;
    const origX = props.block.pos_x;
    const origY = props.block.pos_y;

    const move = (e) => {
        const dx = (e.clientX - startX) / props.scale;
        const dy = (e.clientY - startY) / props.scale;
        emit('update', {
            pos_x: clamp(origX + dx, 0, props.bgWidth - width.value),
            pos_y: clamp(origY + dy, 0, props.bgHeight - height.value),
        });
    };

    const up = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
}

function startResize(event) {
    emit('select');
    event.preventDefault();
    event.stopPropagation();
    event.target.setPointerCapture(event.pointerId);

    const startX = event.clientX;
    const startY = event.clientY;
    const origW = width.value;
    const origH = height.value;

    const move = (e) => {
        const dw = (e.clientX - startX) / props.scale;
        const dh = (e.clientY - startY) / props.scale;
        emit('update', {
            width: clamp(origW + dw, 20, props.bgWidth - props.block.pos_x),
            height: clamp(origH + dh, 20, props.bgHeight - props.block.pos_y),
        });
    };

    const up = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
}

function nudge(event) {
    const step = (event.shiftKey ? 10 : 1);
    const moves = {
        ArrowLeft: { pos_x: clamp(props.block.pos_x - step, 0, props.bgWidth - width.value) },
        ArrowRight: { pos_x: clamp(props.block.pos_x + step, 0, props.bgWidth - width.value) },
        ArrowUp: { pos_y: clamp(props.block.pos_y - step, 0, props.bgHeight - height.value) },
        ArrowDown: { pos_y: clamp(props.block.pos_y + step, 0, props.bgHeight - height.value) },
    };
    if (moves[event.key]) {
        event.preventDefault();
        emit('update', moves[event.key]);
    }
}
</script>

<template>
    <div
        class="absolute flex cursor-grab touch-none select-none items-center overflow-hidden outline-offset-2 active:cursor-grabbing"
        :class="[
            selected ? 'outline outline-2 outline-brand-500' : 'outline-dashed outline-1 outline-slate-400/60 hover:outline-brand-400',
            !block.is_visible && 'opacity-40',
        ]"
        :style="style"
        tabindex="0"
        @pointerdown="startDrag"
        @keydown="nudge"
    >
        <template v-if="block.type === 'qrcode'">
            <div class="flex h-full w-full items-center justify-center bg-white/70">
                <svg class="h-3/4 w-3/4 text-slate-700" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v3h-3v-3zm-5 0h3v3h-3v-3zm0 5h3v3h-3v-3zm5 0h3v3h-3v-3z" />
                </svg>
            </div>
        </template>
        <template v-else-if="block.type === 'image'">
            <img
                v-if="block.value && !block.is_dynamic"
                :src="`/storage/${block.value}`"
                class="pointer-events-none h-full w-full object-contain"
                alt=""
            />
            <div v-else class="flex h-full w-full items-center justify-center bg-slate-200/60 text-xs text-slate-500">Image</div>
        </template>
        <span v-else class="w-full leading-tight" :style="{ textAlign: block.text_align }" v-text="displayText" />

        <!-- Resize handle -->
        <div
            v-if="selected"
            class="absolute bottom-0 right-0 h-3.5 w-3.5 cursor-nwse-resize rounded-tl bg-brand-500"
            @pointerdown="startResize"
        />
    </div>
</template>
