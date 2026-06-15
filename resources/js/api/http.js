import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

// Organizations reuse the admin SPA's screens, which call /admin/* endpoints.
// Transparently route those to the org-scoped /org/* endpoints for org users
// (an org never legitimately calls /admin). Stores also set the base via
// apiBase(); this is the safety net for any direct page-level call.
http.interceptors.request.use((config) => {
    if (config.url && useAuthStore().isOrganization) {
        config.url = config.url.replace(/^\/?admin\//, '/org/');
    }
    return config;
});

let csrfReady = false;

export async function ensureCsrf() {
    if (!csrfReady) {
        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
        csrfReady = true;
    }
}

// Public routes that must never bounce a guest to login on a 401.
const PUBLIC_PATH = /^\/(login|verify|changelog|invite|forgot-password|reset-password)/;

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const url = error.config?.url || '';
        // The auth probe (`/auth/me`) 401s for guests by design — that's how
        // the app learns it's logged out, not a session expiring mid-action.
        const isAuthProbe = url.includes('auth/me');

        if (error.response?.status === 401 && !isAuthProbe) {
            const path = window.location.pathname;
            if (path !== '/' && !PUBLIC_PATH.test(path)) {
                window.location.assign('/login');
            }
        }
        if (error.response?.status === 419) {
            csrfReady = false;
        }
        return Promise.reject(error);
    }
);

export default http;
