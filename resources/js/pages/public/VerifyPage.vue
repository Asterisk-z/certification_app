<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/api/http';

const route = useRoute();
const number = ref('');
const loading = ref(false);
const result = ref(null);
const certificate = ref(null);

const resultStyles = {
    valid: { box: 'bg-emerald-50 dark:bg-emerald-950/50 ring-emerald-200 dark:ring-emerald-900', badge: 'bg-emerald-600', label: 'Valid certificate' },
    expired: { box: 'bg-orange-50 ring-orange-200 dark:ring-orange-900', badge: 'bg-orange-500', label: 'Expired certificate' },
    revoked: { box: 'bg-rose-50 dark:bg-rose-950/50 ring-rose-200 dark:ring-rose-900', badge: 'bg-rose-600', label: 'Revoked certificate' },
    renewed: { box: 'bg-violet-50 ring-violet-200 dark:ring-violet-900', badge: 'bg-violet-600', label: 'Superseded (renewed)' },
    not_found: { box: 'bg-slate-50 dark:bg-slate-950 ring-slate-200 dark:ring-slate-800', badge: 'bg-slate-500', label: 'Not found' },
};

async function verify() {
    if (!number.value.trim()) return;
    loading.value = true;
    result.value = null;
    certificate.value = null;
    try {
        const { data } = await http.get('/verify', { params: { number: number.value.trim() } });
        result.value = data.result;
        certificate.value = data.certificate;
    } catch (e) {
        result.value = 'not_found';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    // QR codes land here with ?number= prefilled.
    if (route.query.number) {
        number.value = String(route.query.number);
        verify();
    }
});
</script>

<template>
    <div class="bg-gradient-to-b from-brand-700 to-brand-900 px-4 py-16 text-center sm:py-24">
        <h1 class="mx-auto max-w-2xl text-3xl font-extrabold text-white sm:text-4xl">
            Verify a certificate
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-brand-100">
            Enter the certificate number (or scan the QR code on the document) to instantly confirm its authenticity.
        </p>

        <form class="mx-auto mt-8 flex max-w-xl flex-col gap-3 sm:flex-row" @submit.prevent="verify">
            <input
                v-model="number"
                type="text"
                placeholder="e.g. HSE-000123"
                class="w-full rounded-xl border-0 px-4 py-3.5 text-center font-mono text-slate-900 dark:text-slate-100 shadow-lg ring-1 ring-white/20 placeholder:font-sans placeholder:text-slate-400 focus:ring-2 focus:ring-white sm:text-left"
                aria-label="Certificate number"
            />
            <button
                type="submit"
                :disabled="loading || !number.trim()"
                class="shrink-0 rounded-xl bg-white dark:bg-slate-900 px-6 py-3.5 font-semibold text-brand-700 dark:text-brand-300 shadow-lg hover:bg-brand-50 dark:hover:bg-brand-900/20 disabled:opacity-60"
            >
                {{ loading ? 'Checking…' : 'Verify' }}
            </button>
        </form>
    </div>

    <div class="mx-auto max-w-xl px-4 pb-16">
        <div
            v-if="result"
            class="-mt-8 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl ring-1 sm:p-8"
            :class="resultStyles[result].box"
        >
            <span
                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold text-white"
                :class="resultStyles[result].badge"
            >
                <svg v-if="result === 'valid'" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M12 5a7 7 0 100 14 7 7 0 000-14z" />
                </svg>
                {{ resultStyles[result].label }}
            </span>

            <template v-if="certificate">
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Certificate number</dt>
                        <dd class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ certificate.number }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Holder</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ certificate.holder }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Credential</dt>
                        <dd class="text-right text-slate-900 dark:text-slate-100">{{ certificate.template }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Issued</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ certificate.issue_date }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ result === 'expired' ? 'Expired' : 'Valid until' }}</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ certificate.expiry_date || 'No expiry' }}</dd>
                    </div>
                    <div v-if="certificate.revoked_at" class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Revoked on</dt>
                        <dd class="font-medium text-rose-600 dark:text-rose-400">{{ certificate.revoked_at }}</dd>
                    </div>
                </dl>

                <a
                    v-if="certificate.view_url"
                    :href="certificate.view_url"
                    target="_blank"
                    rel="noopener"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 font-semibold text-white shadow-sm hover:bg-brand-700"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    </svg>
                    View certificate
                </a>
            </template>
            <p v-else class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                No valid certificate matches that number. Check for typos, or contact the issuer if you believe this is
                an error.
            </p>
        </div>

        <!-- How it works -->
        <div v-if="!result" class="mt-12 grid grid-cols-1 gap-6 text-center sm:grid-cols-3">
            <div v-for="(item, i) in [
                { title: 'Find the number', text: 'It is printed on the certificate, e.g. HSE-000123.' },
                { title: 'Enter or scan', text: 'Type it above, or scan the QR code with your phone.' },
                { title: 'Get instant proof', text: 'See the holder, issuer, dates and live validity status.' },
            ]" :key="item.title">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40 font-bold text-brand-700 dark:text-brand-300">
                    {{ i + 1 }}
                </div>
                <h2 class="mt-3 font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ item.text }}</p>
            </div>
        </div>
    </div>
</template>
