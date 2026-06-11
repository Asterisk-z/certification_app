<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTemplatesStore } from '@/stores/templates';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const store = useTemplatesStore();
const ui = useUiStore();

const isEdit = computed(() => !!route.params.uuid);
const loading = ref(false);
const errors = ref({});
const preview = ref(null);

const form = reactive({
    name: '',
    code: '',
    duration: '',
    duration_type: '',
    status: 'draft',
    background: null,
});

onMounted(async () => {
    if (isEdit.value) {
        const template = await store.fetchOne(route.params.uuid);
        form.name = template.name;
        form.code = template.code;
        form.duration = template.duration ?? '';
        form.duration_type = template.duration_type ?? '';
        form.status = template.status;
        if (template.background_image) {
            preview.value = `/storage/${template.background_image}`;
        }
    }
});

function pickFile(event) {
    const file = event.target.files[0];
    if (!file) return;
    form.background = file;
    preview.value = URL.createObjectURL(file);
}

async function submit() {
    loading.value = true;
    errors.value = {};

    const fd = new FormData();
    fd.append('name', form.name);
    fd.append('code', form.code);
    if (form.duration) fd.append('duration', form.duration);
    if (form.duration_type) fd.append('duration_type', form.duration_type);
    if (isEdit.value) fd.append('status', form.status);
    if (form.background) fd.append('background', form.background);

    try {
        const template = isEdit.value
            ? await store.update(route.params.uuid, fd)
            : await store.create(fd);
        ui.success(isEdit.value ? 'Template updated.' : 'Template created. Now arrange the layout.');
        router.push({ name: 'admin.templates.designer', params: { uuid: template.uuid } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            ui.error(e.response?.data?.message || 'Could not save the template.');
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ isEdit ? 'Edit template' : 'New template' }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Set the basics here — you'll position the content blocks in the designer next.
        </p>

        <form class="mt-6 space-y-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="e.g. Fire Safety Training"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <p v-if="errors.name" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.name[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Code / prefix</label>
                    <input
                        v-model="form.code"
                        type="text"
                        required
                        placeholder="e.g. FST"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 uppercase shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <p class="mt-1 text-xs text-slate-400">Credential numbers become CODE-000001.</p>
                    <p v-if="errors.code" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.code[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Validity duration</label>
                    <input
                        v-model="form.duration"
                        type="number"
                        min="1"
                        placeholder="Leave empty for no expiry"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <p v-if="errors.duration" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.duration[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Duration type</label>
                    <select
                        v-model="form.duration_type"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="">—</option>
                        <option value="day">Day(s)</option>
                        <option value="month">Month(s)</option>
                        <option value="year">Year(s)</option>
                    </select>
                    <p v-if="errors.duration_type" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.duration_type[0] }}</p>
                </div>
                <div v-if="isEdit">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                    <select
                        v-model="form.status"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="draft">Draft</option>
                        <option value="ready">Ready</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Background image</label>
                <p class="text-xs text-slate-400">The image's pixel size defines the credential canvas.</p>
                <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start">
                    <label
                        class="flex aspect-[1123/794] w-full max-w-xs cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-sm text-slate-500 dark:text-slate-400 hover:border-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/20"
                    >
                        <img v-if="preview" :src="preview" alt="Background preview" class="h-full w-full rounded-xl object-cover" />
                        <span v-else>Click to upload (PNG, JPG, WebP)</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="pickFile" />
                    </label>
                </div>
                <p v-if="errors.background" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.background[0] }}</p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row sm:justify-end">
                <router-link
                    :to="{ name: 'admin.templates' }"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                >
                    Cancel
                </router-link>
                <button
                    type="submit"
                    :disabled="loading"
                    class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                >
                    {{ loading ? 'Saving…' : isEdit ? 'Save changes' : 'Create & open designer' }}
                </button>
            </div>
        </form>
    </div>
</template>
