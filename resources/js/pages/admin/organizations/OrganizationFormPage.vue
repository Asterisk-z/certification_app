<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOrganizationsStore } from '@/stores/organizations';
import { useUiStore } from '@/stores/ui';

const FEATURES = [
    { key: 'templates', label: 'Templates & designer' },
    { key: 'recipients', label: 'Recipients' },
    { key: 'groups', label: 'Groups' },
    { key: 'certificates', label: 'Credentials' },
];

// Per-organization usage caps (blank = unlimited). Mirrors Organization::LIMITS.
const LIMIT_TOTALS = [
    { key: 'certificates', label: 'Credentials' },
    { key: 'certificate_admins', label: 'Certificate admins' },
    { key: 'templates', label: 'Templates' },
    { key: 'groups', label: 'Groups' },
    { key: 'recipients', label: 'Recipients' },
];

const LIMIT_SCOPED = [
    { key: 'certificates_per_recipient', label: 'Credentials per recipient' },
    { key: 'groups_per_recipient', label: 'Groups per recipient' },
    { key: 'certificates_per_group', label: 'Credentials per group' },
];

const ALL_LIMITS = [...LIMIT_TOTALS, ...LIMIT_SCOPED];

function limitsFromOrg(orgLimits) {
    return Object.fromEntries(
        ALL_LIMITS.map((l) => [l.key, orgLimits && orgLimits[l.key] != null ? orgLimits[l.key] : '']),
    );
}

const route = useRoute();
const router = useRouter();
const store = useOrganizationsStore();
const ui = useUiStore();

const editing = computed(() => Boolean(route.params.uuid));
const saving = ref(false);
const errors = ref({});
const logo = ref(null);
const existingLogo = ref(null);

const form = reactive({
    name: '',
    email: '',
    code: '',
    description: '',
    status: 'active',
    features: { templates: true, recipients: true, groups: true, certificates: true },
    limits: limitsFromOrg(null),
    provision: 'link',
    password: '',
});

onMounted(async () => {
    if (!editing.value) return;
    const org = await store.fetchOne(route.params.uuid);
    Object.assign(form, {
        name: org.name,
        email: org.email,
        code: org.code,
        description: org.description || '',
        status: org.status,
        features: { templates: true, recipients: true, groups: true, certificates: true, ...(org.features || {}) },
        limits: limitsFromOrg(org.limits),
        provision: 'link',
        password: '',
    });
    existingLogo.value = org.logo_path;
});

function buildPayload() {
    const fd = new FormData();
    fd.append('name', form.name);
    fd.append('email', form.email);
    if (form.code) fd.append('code', form.code);
    if (form.description) fd.append('description', form.description);
    fd.append('status', form.status);
    FEATURES.forEach((f) => fd.append(`features[${f.key}]`, form.features[f.key] ? '1' : '0'));
    ALL_LIMITS.forEach((l) => {
        const v = form.limits[l.key];
        if (v !== '' && v !== null && v !== undefined) fd.append(`limits[${l.key}]`, v);
    });
    if (!editing.value) fd.append('provision', form.provision);
    if (form.password) {
        if (!editing.value) fd.append('provision', 'password');
        fd.append('password', form.password);
    }
    if (logo.value) fd.append('logo', logo.value);
    return fd;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (editing.value) {
            await store.update(route.params.uuid, buildPayload());
            ui.success('Organization updated.');
        } else {
            await store.create(buildPayload());
            ui.success('Organization created.');
        }
        router.push({ name: 'admin.organizations' });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            ui.error(e.response?.data?.message || 'Could not save the organization.');
        }
    } finally {
        saving.value = false;
    }
}

async function resendSetup() {
    try {
        const res = await store.resendSetup(route.params.uuid);
        ui.success(res.message || 'Setup link sent.');
    } catch (e) {
        ui.error(e.response?.data?.message || 'Could not send the setup link.');
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit organization' : 'New organization' }}</h1>

        <form class="mt-6 space-y-5 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                    <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <p v-if="errors.name" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.name[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Login email</label>
                    <input v-model="form.email" type="email" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <p v-if="errors.email" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.email[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Code <span class="font-normal text-slate-400">(auto if empty)</span></label>
                    <input v-model="form.code" type="text" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 font-mono shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <p v-if="errors.code" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.code[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                    <select v-model="form.status" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive (cannot log in)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Logo</label>
                <div class="mt-1 flex items-center gap-3">
                    <img v-if="existingLogo" :src="`/storage/${existingLogo}`" class="h-10 w-10 rounded object-contain bg-white ring-1 ring-slate-200" alt="" />
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="logo = $event.target.files[0]" />
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Allowed features</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <label v-for="f in FEATURES" :key="f.key" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.features[f.key]" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500" />
                        {{ f.label }}
                    </label>
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Usage limits <span class="font-normal text-slate-400">(blank = unlimited)</span>
                </h2>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div v-for="l in LIMIT_TOTALS" :key="l.key">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ l.label }}</label>
                        <input v-model="form.limits[l.key]" type="number" min="1" placeholder="Unlimited"
                            class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        <p v-if="errors[`limits.${l.key}`]" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors[`limits.${l.key}`][0] }}</p>
                    </div>
                </div>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Per recipient / per group</h3>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div v-for="l in LIMIT_SCOPED" :key="l.key">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ l.label }}</label>
                        <input v-model="form.limits[l.key]" type="number" min="1" placeholder="Unlimited"
                            class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        <p v-if="errors[`limits.${l.key}`]" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors[`limits.${l.key}`][0] }}</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Login access</h2>
                <div v-if="!editing" class="mt-3 space-y-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.provision" type="radio" value="link" class="border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Email a setup link (they choose their own password)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.provision" type="radio" value="password" class="border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Set an initial password now
                    </label>
                </div>
                <div v-if="editing || form.provision === 'password'" class="mt-3">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ editing ? 'Reset password (optional)' : 'Initial password' }}
                    </label>
                    <input v-model="form.password" type="text" :required="!editing && form.provision === 'password'" autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    <p v-if="errors.password" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ errors.password[0] }}</p>
                </div>
                <button v-if="editing" type="button" class="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700" @click="resendSetup">
                    Send setup link
                </button>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 pt-4 sm:flex-row sm:justify-end">
                <router-link :to="{ name: 'admin.organizations' }" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">Cancel</router-link>
                <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ saving ? 'Saving…' : editing ? 'Save changes' : 'Create organization' }}
                </button>
            </div>
        </form>
    </div>
</template>
