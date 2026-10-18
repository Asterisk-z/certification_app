<script setup>
import { computed } from 'vue';

/**
 * Clickable column header. First click sorts ascending, a second click flips
 * the direction. Emits `sort` with { field, dir } for the page to apply.
 */
const props = defineProps({
    label: { type: String, required: true },
    field: { type: String, required: true },
    sort: { type: String, default: '' },
    dir: { type: String, default: 'asc' },
    align: { type: String, default: 'left' }, // left | right
});

const emit = defineEmits(['sort']);

const active = computed(() => props.sort === props.field);

function toggle() {
    emit('sort', { field: props.field, dir: active.value && props.dir === 'asc' ? 'desc' : 'asc' });
}
</script>

<template>
    <th
        class="px-4 py-3"
        :class="align === 'right' ? 'text-right' : 'text-left'"
        :aria-sort="active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none'"
    >
        <button
            type="button"
            class="group inline-flex items-center gap-1 uppercase tracking-wide hover:text-slate-900 dark:hover:text-slate-100"
            :class="active ? 'text-slate-900 dark:text-slate-100' : ''"
            :title="`Sort by ${label.toLowerCase()}`"
            @click="toggle"
        >
            {{ label }}
            <svg class="h-3.5 w-3.5" :class="active ? '' : 'opacity-30 group-hover:opacity-60'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path v-if="active && dir === 'asc'" stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                <path v-else-if="active" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M16 15l-4 4-4-4" />
            </svg>
        </button>
    </th>
</template>
