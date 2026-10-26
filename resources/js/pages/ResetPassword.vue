<template>
    <div class="customer-screen">
        <form class="auth" @submit.prevent="submit">
            <div class="auth-brand">
                <span class="auth-brand-name">Mavies Bugasan</span>
            </div>

            <h2>Reset Password</h2>
            <p class="sub">Choose a new password for {{ email || 'your account' }}.</p>

            <div class="field">
                <label class="label" for="password">New Password</label>
                <input id="password" v-model="form.password" class="input" type="password" autocomplete="new-password" placeholder="At least 8 characters">
            </div>

            <div class="field">
                <label class="label" for="confirm">Confirm Password</label>
                <input id="confirm" v-model="form.confirm" class="input" type="password" autocomplete="new-password" placeholder="Repeat the new password">
            </div>

            <p v-if="error" class="error">{{ error }}</p>

            <button class="btn btn-dark" type="submit" :disabled="busy">
                {{ busy ? 'Saving…' : 'RESET PASSWORD' }}
            </button>

            <div class="switch">
                Back to <router-link to="/login">Sign In</router-link>
            </div>
        </form>
    </div>
</template>

<script>
import api from '../api';
import { errMsg } from '../util';

export default {
    name: 'ResetPassword',
    data() {
        return {
            form: { password: '', confirm: '' },
            email: '',
            token: '',
            error: '',
            busy: false,
        };
    },
    created() {
        this.email = this.$route.query.email || '';
        this.token = this.$route.query.token || '';
        if (!this.email || !this.token) {
            this.error = 'This reset link is incomplete. Request a new one.';
        }
    },
    methods: {
        async submit() {
            if (!this.email || !this.token) {
                this.error = 'This reset link is invalid. Request a new one.';
                return;
            }
            if (this.form.password !== this.form.confirm) {
                this.error = 'The passwords do not match.';
                return;
            }
            this.error = '';
            this.busy = true;
            try {
                await api.post('/api/customer/password/reset', {
                    email: this.email,
                    token: this.token,
                    password: this.form.password,
                    password_confirmation: this.form.confirm,
                });
                this.$store.dispatch('toast', { text: 'Password reset. Please sign in.', variant: 'success' });
                this.$router.replace('/login');
            } catch (e) {
                this.error = errMsg(e, 'Could not reset your password.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>
