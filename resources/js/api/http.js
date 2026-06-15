import axios from 'axios';

const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
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
