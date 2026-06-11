<script setup>
const props = defineProps({
    block: { type: Object, default: null },
});

const emit = defineEmits(['change', 'remove', 'save-block']);

const fonts = ['Arial', 'Inter', 'Georgia', 'Times New Roman', 'Courier New', 'Verdana', 'Tahoma', 'Trebuchet MS'];
const weights = ['normal', 'bold', '300', '400', '500', '600', '700', '800'];

function set(field, value) {
    emit('change', { [field]: value });
}
</script>

<template>
    <div v-if="!block" class="p-4 text-sm text-slate-500 dark:text-slate-400">
        Select a block on the canvas to edit its properties.
    </div>

    <div v-else class="space-y-4 p-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ block.name }}</h3>
            <span class="rounded bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs uppercase text-slate-500 dark:text-slate-400">{{ block.type }}</span>
        </div>

        <p v-if="block.is_dynamic" class="rounded-lg bg-brand-50 dark:bg-brand-900/30 px-3 py-2 text-xs text-brand-800 dark:text-brand-200">
            Dynamic field — filled per recipient from the
            <code class="font-mono">{{ block.slug }}</code> Excel column.
        </p>

        <div v-if="!block.is_dynamic && block.type === 'text'">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Text value</label>
            <textarea
                :value="block.value"
                rows="2"
                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                @input="set('value', $event.target.value)"
            />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">X</label>
                <input
                    type="number" :value="Math.round(block.pos_x)" min="0"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                    @input="set('pos_x', Number($event.target.value))"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Y</label>
                <input
                    type="number" :value="Math.round(block.pos_y)" min="0"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                    @input="set('pos_y', Number($event.target.value))"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Width</label>
                <input
                    type="number" :value="block.width ? Math.round(block.width) : ''" min="10"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                    @input="set('width', Number($event.target.value) || null)"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Height</label>
                <input
                    type="number" :value="block.height ? Math.round(block.height) : ''" min="10"
                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                    @input="set('height', Number($event.target.value) || null)"
                />
            </div>
        </div>

        <template v-if="block.type !== 'image'">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Font size</label>
                    <input
                        type="number" :value="block.font_size" min="6" max="200"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                        @input="set('font_size', Number($event.target.value))"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Color</label>
                    <input
                        type="color" :value="block.font_color"
                        class="mt-1 block h-9 w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm"
                        @input="set('font_color', $event.target.value)"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Font family</label>
                    <select
                        :value="block.font_family"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                        @change="set('font_family', $event.target.value)"
                    >
                        <option v-for="font in fonts" :key="font" :value="font">{{ font }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Weight</label>
                    <select
                        :value="block.font_weight"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 text-sm shadow-sm"
                        @change="set('font_weight', $event.target.value)"
                    >
                        <option v-for="weight in weights" :key="weight" :value="weight">{{ weight }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Alignment</label>
                <div class="mt-1 flex gap-1">
                    <button
                        v-for="align in ['left', 'center', 'right']"
                        :key="align"
                        class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium capitalize"
                        :class="block.text_align === align ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        @click="set('text_align', align)"
                    >
                        {{ align }}
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-2 border-t border-slate-100 dark:border-slate-800 pt-3">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input
                    type="checkbox" :checked="block.is_visible"
                    class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                    @change="set('is_visible', $event.target.checked)"
                />
                Visible on credential
            </label>
        </div>

        <div class="flex gap-2 border-t border-slate-100 dark:border-slate-800 pt-3">
            <button
                class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"
                @click="$emit('save-block')"
            >
                Save block
            </button>
            <button
                v-if="!block.is_default"
                class="rounded-lg border border-rose-200 dark:border-rose-900 px-3 py-2 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                @click="$emit('remove')"
            >
                Remove
            </button>
        </div>
    </div>
</template>
