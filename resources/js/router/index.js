import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { startProgress, doneProgress } from '@/lib/progress';

const routes = [
    // Public — verification is the landing page
    {
        path: '/',
        component: () => import('@/layouts/PublicLayout.vue'),
        children: [
            { path: '', name: 'verify', component: () => import('@/pages/public/VerifyPage.vue') },
        ],
    },

    // Auth
    {
        path: '/',
        component: () => import('@/layouts/AuthLayout.vue'),
        meta: { guestOnly: true },
        children: [
            { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue') },
            { path: 'invite/:uuid', name: 'invite', component: () => import('@/pages/auth/AcceptInvitePage.vue'), meta: { guestOnly: false } },
            { path: 'forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/ForgotPasswordPage.vue') },
            { path: 'reset-password', name: 'reset-password', component: () => import('@/pages/auth/ResetPasswordPage.vue') },
        ],
    },

    // Admin
    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true, role: 'admin' },
        children: [
            { path: '', name: 'admin.dashboard', component: () => import('@/pages/admin/DashboardPage.vue') },
            { path: 'templates', name: 'admin.templates', component: () => import('@/pages/admin/templates/TemplateListPage.vue') },
            { path: 'templates/create', name: 'admin.templates.create', component: () => import('@/pages/admin/templates/TemplateFormPage.vue') },
            { path: 'templates/:uuid/edit', name: 'admin.templates.edit', component: () => import('@/pages/admin/templates/TemplateFormPage.vue') },
            { path: 'templates/:uuid/designer', name: 'admin.templates.designer', component: () => import('@/pages/admin/templates/TemplateDesignerPage.vue') },
            { path: 'recipients', name: 'admin.recipients', component: () => import('@/pages/admin/recipients/RecipientListPage.vue') },
            { path: 'groups', name: 'admin.groups', component: () => import('@/pages/admin/recipients/GroupListPage.vue') },
            { path: 'groups/:uuid', name: 'admin.groups.detail', component: () => import('@/pages/admin/recipients/GroupDetailPage.vue') },
            { path: 'certificates', name: 'admin.certificates', component: () => import('@/pages/admin/certificates/CertificateListPage.vue') },
            { path: 'certificates/send', name: 'admin.certificates.send', component: () => import('@/pages/admin/certificates/SendWizardPage.vue') },
            { path: 'certificates/import', name: 'admin.certificates.import', component: () => import('@/pages/admin/certificates/ImportPage.vue') },
            { path: 'certificates/manual', name: 'admin.certificates.manual', component: () => import('@/pages/admin/certificates/ManualCreatePage.vue') },
            { path: 'certificates/:uuid', name: 'admin.certificates.detail', component: () => import('@/pages/admin/certificates/CertificateDetailPage.vue') },
            { path: 'newsletters', name: 'admin.newsletters', component: () => import('@/pages/admin/newsletters/NewsletterListPage.vue') },
            { path: 'newsletters/compose', name: 'admin.newsletters.compose', component: () => import('@/pages/admin/newsletters/NewsletterComposePage.vue') },
            { path: 'logs/mail', name: 'admin.logs.mail', component: () => import('@/pages/admin/logs/MailLogPage.vue') },
            { path: 'logs/activity', name: 'admin.logs.activity', component: () => import('@/pages/admin/logs/ActivityLogPage.vue') },
            { path: 'changelog', name: 'admin.changelog', component: () => import('@/pages/admin/ChangelogPage.vue') },
        ],
    },

    // Recipient portal
    {
        path: '/portal',
        component: () => import('@/layouts/RecipientLayout.vue'),
        meta: { requiresAuth: true, role: 'recipient' },
        children: [
            { path: '', name: 'portal.dashboard', component: () => import('@/pages/recipient/DashboardPage.vue') },
            { path: 'certificates/:uuid', name: 'portal.certificate', component: () => import('@/pages/recipient/CertificateViewPage.vue') },
            { path: 'profile', name: 'portal.profile', component: () => import('@/pages/recipient/ProfilePage.vue') },
        ],
    },

    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

// Once signed in, warm every route's lazy chunk on idle so later page
// changes are instant (the chunk is already downloaded). Done once, and
// only for authenticated users so public visitors don't pull the whole app.
let prefetched = false;

function prefetchRouteChunks() {
    router.getRoutes().forEach((route) => {
        Object.values(route.components ?? {}).forEach((loader) => {
            if (typeof loader === 'function') {
                loader().catch(() => {});
            }
        });
    });
}

function prefetchOnIdle() {
    if (prefetched || !useAuthStore().isAuthenticated) {
        return;
    }
    prefetched = true;
    const schedule = window.requestIdleCallback || ((cb) => setTimeout(cb, 1500));
    schedule(prefetchRouteChunks);
}

// Show the top progress bar for the whole navigation (chunk fetch + guards).
router.beforeEach(() => {
    startProgress();
});

router.afterEach(() => {
    doneProgress();
    prefetchOnIdle();
});

router.onError(() => {
    doneProgress();
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.hydrate();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.role && auth.user?.role !== to.meta.role) {
        // Send the user to the dashboard that matches their role.
        if (auth.isAdmin) return { name: 'admin.dashboard' };
        if (auth.isRecipient) return { name: 'portal.dashboard' };
        return { name: 'login' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return auth.isAdmin ? { name: 'admin.dashboard' } : { name: 'portal.dashboard' };
    }

    return true;
});

export default router;
