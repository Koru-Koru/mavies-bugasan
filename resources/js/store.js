import Vue from 'vue';
import Vuex from 'vuex';
import axios from './api';

Vue.use(Vuex);

export default new Vuex.Store({
    state: {
        auth: { guard: null, user: null, ready: false },
        toast: { show: false, text: '', variant: 'success' },
    },

    getters: {
        isAdmin: (state) => state.auth.guard === 'admin',
        isCustomer: (state) => state.auth.guard === 'customer',
        authUser: (state) => state.auth.user,
        authReady: (state) => state.auth.ready,
    },

    mutations: {
        SET_AUTH(state, { guard, user }) {
            state.auth.guard = guard;
            state.auth.user = user;
            state.auth.ready = true;
        },
        CLEAR_AUTH(state) {
            state.auth.guard = null;
            state.auth.user = null;
            state.auth.ready = true;
        },
        SET_READY(state, value) {
            state.auth.ready = value;
        },
        TOAST(state, payload) {
            state.toast = { show: true, text: payload.text, variant: payload.variant || 'success' };
        },
        CLEAR_TOAST(state) {
            state.toast.show = false;
        },
    },

    actions: {
        // Session restore: ask for the guard the current route needs first, so a
        // browser that still holds both an admin and a customer session lands on
        // the right one instead of always favouring admin.
        async boot({ commit }, preferredGuard = null) {
            const order = preferredGuard === 'admin' ? ['admin', 'customer'] : ['customer', 'admin'];

            for (const guard of order) {
                try {
                    const r = await axios.get(`/api/${guard}/me`);
                    commit('SET_AUTH', {
                        guard,
                        user: guard === 'admin' ? r.data.admin : r.data.customer,
                    });
                    return;
                } catch (e) {
                    /* try the next guard */
                }
            }

            commit('SET_READY', true);
        },

        async login({ commit }, { guard, payload }) {
            const path = guard === 'admin' ? '/api/admin/login' : '/api/customer/login';
            const r = await axios.post(path, payload);
            commit('SET_AUTH', {
                guard,
                user: guard === 'admin' ? r.data.admin : r.data.customer,
            });
        },

        async register(_ctx, payload) {
            const r = await axios.post('/api/customer/register', payload);
            return r.data;
        },

        async logout({ commit }, guard) {
            try {
                await axios.post(`/api/${guard}/logout`);
            } catch (e) {
                /* session may already be gone — clear locally either way */
            }
            commit('CLEAR_AUTH');
        },

        toast({ commit }, payload) {
            window.setTimeout(() => commit('CLEAR_TOAST'), 5000);
            commit('TOAST', payload);
        },
        clearToast({ commit }) {
            commit('CLEAR_TOAST');
        },
    },
});