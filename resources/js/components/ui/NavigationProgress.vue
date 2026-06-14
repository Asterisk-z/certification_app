<script setup>
import { progress } from '@/lib/progress';
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-0 z-[200] h-0.5"
        :style="{ opacity: progress.active ? 1 : 0 }"
        style="transition: opacity 0.25s ease"
    >
        <div
            class="h-full bg-brand-600 shadow-[0_0_8px_var(--color-brand-500)]"
            :style="{
                width: progress.value + '%',
                transition: 'width 0.18s ease',
            }"
        />
    </div>
</template>
