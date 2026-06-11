<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-slate-50 dark:bg-slate-950 px-4 text-center">
        <p class="text-6xl font-extrabold text-brand-600 dark:text-brand-400">404</p>
        <h1 class="mt-4 text-xl font-bold text-slate-900 dark:text-slate-100">Page not found</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">The page you're looking for doesn't exist or has moved.</p>
        <router-link to="/" class="mt-6 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
            Go home
        </router-link>
    </div>
</template>
