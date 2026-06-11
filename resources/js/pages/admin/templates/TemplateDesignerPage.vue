<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute } from 'vue-router';
import { useTemplatesStore } from '@/stores/templates';
import { useUiStore } from '@/stores/ui';
import DesignerBlock from '@/components/designer/DesignerBlock.vue';
import BlockPropertiesPanel from '@/components/designer/BlockPropertiesPanel.vue';

const route = useRoute();
const store = useTemplatesStore();
const ui = useUiStore();

const DEFAULT_BLOCK_FONT_SIZE = 60;
const DEFAULT_BLOCK_WIDTH = 300;

const template = ref(null);
const blocks = ref([]);
const selectedUuid = ref(null);
const dirty = ref(false);
const saving = ref(false);
const canvasWrap = ref(null);
const displayWidth = ref(800);

const addModal = ref(false);
const addForm = reactive({ name: '', type: 'text', value: '', is_dynamic: false, image: null });
const addLoading = ref(false);

const selected = computed(() => blocks.value.find((b) => b.uuid === selectedUuid.value) || null);
const scale = computed(() => (template.value ? displayWidth.value / template.value.bg_width : 1));
const displayHeight = computed(() =>
    template.value ? template.value.bg_height * scale.value : 0
);

let resizeObserver = null;

onMounted(async () => {
    template.value = await store.fetchOne(route.params.uuid);
    blocks.value = template.value.blocks.map((b) => ({ ...b }));

    resizeObserver = new ResizeObserver(() => {
        if (canvasWrap.value && template.value) {
            displayWidth.value = Math.min(canvasWrap.value.clientWidth, template.value.bg_width);
        }
    });
    resizeObserver.observe(canvasWrap.value);
});

onBeforeUnmount(() => resizeObserver?.disconnect());

onBeforeRouteLeave(() => {
    if (dirty.value && !window.confirm('You have unsaved layout changes. Leave anyway?')) {
        return false;
    }
});

const rowRefs = new Map();

function setRowRef(uuid, el) {
    if (el) rowRefs.set(uuid, el);
    else rowRefs.delete(uuid);
}

function placeholderFor(block) {
    return '{{' + block.slug + '}}';
}

// Selecting a block on the canvas brings its table row into view.
watch(selectedUuid, (uuid) => {
    if (uuid) rowRefs.get(uuid)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
});

function patchBlock(uuid, patch) {
    const block = blocks.value.find((b) => b.uuid === uuid);
    if (block) {
        Object.assign(block, patch);
        dirty.value = true;
    }
}

async function saveLayout() {
    saving.value = true;
    try {
        const payload = blocks.value.map((b) => ({
            uuid: b.uuid,
            pos_x: Math.round(b.pos_x * 100) / 100,
            pos_y: Math.round(b.pos_y * 100) / 100,
            width: b.width ?? null,
            height: b.height ?? null,
        }));
        const updated = await store.saveLayout(template.value.uuid, payload);
        template.value = updated;
        dirty.value = false;
        ui.success('Layout saved — template is ready to send.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not save the layout.');
    } finally {
        saving.value = false;
    }
}

async function saveSelectedBlock() {
    if (!selected.value) return;
    try {
        const b = selected.value;
        await store.updateBlock(b.uuid, {
            name: b.name,
            type: b.type,
            value: b.value,
            is_visible: b.is_visible,
            pos_x: b.pos_x,
            pos_y: b.pos_y,
            width: b.width,
            height: b.height,
            font_family: b.font_family,
            font_size: b.font_size,
            font_color: b.font_color,
            font_weight: b.font_weight,
            text_align: b.text_align,
        });
        ui.success('Block saved.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not save the block.');
    }
}

async function removeSelectedBlock() {
    if (!selected.value || selected.value.is_default) return;
    try {
        await store.deleteBlock(selected.value.uuid);
        blocks.value = blocks.value.filter((b) => b.uuid !== selected.value.uuid);
        selectedUuid.value = null;
        ui.success('Block removed.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not remove the block.');
    }
}

async function addBlock() {
    addLoading.value = true;
    try {
        let payload;
        if (addForm.type === 'image' && addForm.image) {
            payload = new FormData();
            payload.append('name', addForm.name);
            payload.append('type', 'image');
            payload.append('image', addForm.image);
        } else {
            payload = {
                name: addForm.name,
                type: addForm.type,
                value: addForm.is_dynamic ? null : addForm.value,
                is_dynamic: addForm.is_dynamic,
                pos_x: 40,
                pos_y: 40,
                width: addForm.type === 'qrcode' ? 120 : DEFAULT_BLOCK_WIDTH,
                height: addForm.type === 'qrcode' ? 120 : null,
                font_size: DEFAULT_BLOCK_FONT_SIZE,
            };
        }
        const block = await store.addBlock(template.value.uuid, payload);
        blocks.value.push({ ...block });
        selectedUuid.value = block.uuid;
        addModal.value = false;
        Object.assign(addForm, { name: '', type: 'text', value: '', is_dynamic: false, image: null });
        ui.success('Block added.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not add the block.');
    } finally {
        addLoading.value = false;
    }
}
</script>

<template>
    <div v-if="template">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-bold text-slate-900 dark:text-slate-100">{{ template.name }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Drag blocks into place · canvas {{ template.bg_width }}×{{ template.bg_height }}px
                    <span v-if="dirty" class="ml-1 font-medium text-amber-600 dark:text-amber-400">· unsaved changes</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    @click="addModal = true"
                >
                    + Add block
                </button>
                <button
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    :disabled="saving"
                    @click="saveLayout"
                >
                    {{ saving ? 'Saving…' : 'Save layout & mark ready' }}
                </button>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1fr_320px]">
            <!-- Canvas -->
            <div ref="canvasWrap" class="min-w-0 overflow-auto">
                <div
                    class="relative mx-auto overflow-hidden rounded-lg bg-white shadow ring-1 ring-slate-200 dark:ring-slate-700"
                    :style="{ width: `${displayWidth}px`, height: `${displayHeight}px` }"
                    @pointerdown.self="selectedUuid = null"
                >
                    <img
                        v-if="template.background_image"
                        :src="`/storage/${template.background_image}`"
                        class="pointer-events-none absolute inset-0 h-full w-full"
                        alt=""
                        draggable="false"
                    />
                    <DesignerBlock
                        v-for="block in blocks"
                        :key="block.uuid"
                        :block="block"
                        :scale="scale"
                        :bg-width="template.bg_width"
                        :bg-height="template.bg_height"
                        :selected="block.uuid === selectedUuid"
                        @select="selectedUuid = block.uuid"
                        @update="patchBlock(block.uuid, $event)"
                    />
                </div>
            </div>

            <!-- Properties -->
            <aside class="h-fit rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
                <BlockPropertiesPanel
                    :block="selected"
                    @change="selected && patchBlock(selected.uuid, $event)"
                    @save-block="saveSelectedBlock"
                    @remove="removeSelectedBlock"
                />
            </aside>
        </div>

        <!-- Blocks table -->
        <div class="mt-6 overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-4 py-3 sm:px-6">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Content blocks</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ blocks.length }} block(s) — click a row to select it on the canvas</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 sm:px-6">Block</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Value</th>
                            <th class="px-4 py-3">X</th>
                            <th class="px-4 py-3">Y</th>
                            <th class="px-4 py-3">W × H</th>
                            <th class="px-4 py-3">Font</th>
                            <th class="px-4 py-3">Visible</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="block in blocks"
                            :key="block.uuid"
                            :ref="(el) => setRowRef(block.uuid, el)"
                            class="cursor-pointer transition-colors"
                            :class="block.uuid === selectedUuid
                                ? 'bg-brand-50 dark:bg-brand-900/30 ring-1 ring-inset ring-brand-300 dark:ring-brand-800'
                                : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                            @click="selectedUuid = block.uuid"
                        >
                            <td class="px-4 py-3 sm:px-6">
                                <p class="font-medium" :class="block.uuid === selectedUuid ? 'text-brand-700 dark:text-brand-300' : 'text-slate-900 dark:text-slate-100'">
                                    {{ block.name }}
                                    <span v-if="block.is_default" class="ml-1 rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">default</span>
                                </p>
                                <p v-if="block.is_dynamic" class="font-mono text-xs text-slate-500 dark:text-slate-400" v-text="placeholderFor(block)" />
                            </td>
                            <td class="px-4 py-3 capitalize text-slate-600 dark:text-slate-400">{{ block.type }}</td>
                            <td class="max-w-[180px] truncate px-4 py-3 text-slate-600 dark:text-slate-400">
                                {{ block.is_dynamic ? 'Per recipient' : block.type === 'text' ? block.value : '—' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-400">{{ Math.round(block.pos_x) }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-400">{{ Math.round(block.pos_y) }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-400">
                                {{ block.width ? Math.round(block.width) : 'auto' }} × {{ block.height ? Math.round(block.height) : 'auto' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-400">
                                <template v-if="block.type === 'text'">{{ block.font_size }}px</template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="block.is_visible
                                        ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                                >
                                    {{ block.is_visible ? 'Visible' : 'Hidden' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add block modal -->
        <teleport to="body">
            <div v-if="addModal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-slate-900/50" @click="addModal = false" />
                <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Add content block</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="addBlock">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                            <input
                                v-model="addForm.name" type="text" required
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                            <select
                                v-model="addForm.type"
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="text">Text</option>
                                <option value="image">Image</option>
                                <option value="qrcode">QR code</option>
                            </select>
                        </div>
                        <label v-if="addForm.type === 'text'" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input
                                v-model="addForm.is_dynamic" type="checkbox"
                                class="rounded border-slate-300 dark:border-slate-700 text-brand-600 dark:text-brand-400 focus:ring-brand-500"
                            />
                            Dynamic (filled per recipient, becomes an Excel column)
                        </label>
                        <div v-if="addForm.type === 'text' && !addForm.is_dynamic">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Text value</label>
                            <input
                                v-model="addForm.value" type="text"
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            />
                        </div>
                        <div v-if="addForm.type === 'image'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Image file</label>
                            <input
                                type="file" accept="image/*" required
                                class="mt-1 block w-full text-sm"
                                @change="addForm.image = $event.target.files[0]"
                            />
                        </div>
                        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                @click="addModal = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit" :disabled="addLoading"
                                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                            >
                                {{ addLoading ? 'Adding…' : 'Add block' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </div>

    <div v-else class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">Loading designer…</div>
</template>
