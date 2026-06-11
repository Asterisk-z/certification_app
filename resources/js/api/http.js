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

http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Session expired — drop local auth state and send to login.
            const current = window.location.pathname;
            if (!current.startsWith('/login') && !current.startsWith('/verify') && current !== '/') {
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
