<script setup>
defineProps({
    active: { type: Boolean, default: false },
    orgName: { type: String, default: '' },
});
defineEmits(['clear']);
</script>

<template>
    <div v-if="active" class="mb-4 flex items-center justify-between gap-3 rounded-lg bg-brand-50 dark:bg-brand-900/30 px-4 py-2.5 text-sm text-brand-800 dark:text-brand-200">
        <span>Showing records for <strong>{{ orgName }}</strong>.</span>
        <button class="shrink-0 font-medium underline hover:text-brand-900 dark:hover:text-brand-100" @click="$emit('clear')">Clear filter</button>
    </div>
</template>
