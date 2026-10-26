import axios from 'axios';
import router from './router';
import store from './store';

// Session CSRF tokens are rotated by login/register/logout. When the server
// answers 419 (token mismatch) we fetch a fresh token and replay the request
// once — the browser already holds the regenerated session cookie, so the
// retry succeeds.
let refreshing = null;

function refreshToken() {
    if (!refreshing) {
        refreshing = axios
            .get('/api/csrf-token')
            .then((response) => {
                const token = response.data.token;
                axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
                return token;
            })
            .finally(() => {
                refreshing = null;
            });
    }
    return refreshing;
}

// One interceptor for the whole app.
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response ? error.response.status : null;
        const config = error.config || {};
        const url = config.url || '';
        const isAuthAttempt = /\/login|\/register/.test(url);

        if (status === 419 && !config._csrfRetried) {
            config._csrfRetried = true;
            try {
                await refreshToken();
                return axios(config);
            } catch (e) {
                // token refresh failed; surface the original error
            }
        }

        if (status === 401 && !isAuthAttempt && store.state.auth.guard) {
            const guard = store.state.auth.guard;
            store.commit('CLEAR_AUTH');
            router.push(guard === 'admin' ? '/admin/login' : '/login');
        }

        return Promise.reject(error);
    },
);

export default axios;