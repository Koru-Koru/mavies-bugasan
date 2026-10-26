<template>
    <div class="customer-screen">
        <form class="auth" @submit.prevent="submit">
            <div class="auth-brand">
                <span class="auth-brand-name">Mavies Bugasan</span>
            </div>

            <h2>Forgot Password</h2>
            <p class="sub">Enter your email and we'll send a reset link.</p>

            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" v-model.trim="email" class="input" type="email" autocomplete="email" placeholder="you@email.com">
            </div>

            <p v-if="error" class="error">{{ error }}</p>
            <p v-if="done" class="hint">{{ done }}</p>

            <button class="btn btn-dark" type="submit" :disabled="busy">
                {{ busy ? 'Sending…' : 'SEND RESET LINK' }}
            </button>

            <div class="switch">
                Remembered it? <router-link to="/login">Sign In</router-link>
            </div>
        </form>
    </div>
</template>

<script>
import api from '../api';
import { errMsg } from '../util';

export default {
    name: 'ForgotPassword',
    data() {
        return { email: '', error: '', done: '', busy: false };
    },
    methods: {
        async submit() {
            if (!this.email) {
                this.error = 'Enter your email address.';
                return;
            }
            this.error = '';
            this.busy = true;
            try {
                const r = await api.post('/api/customer/password/forgot', { email: this.email });
                this.done = r.data.message;
            } catch (e) {
                this.error = errMsg(e, 'Could not send the reset link.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>
