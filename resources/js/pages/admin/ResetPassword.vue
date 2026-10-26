<template>
    <main class="container py-5">
        <div class="card card-raised mx-auto" style="max-width: 420px;">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 text-center mb-4">Set a new password</h1>
                <p class="text-center text-muted small mb-4">Choose a new password for {{ email || 'your account' }}.</p>

                <form @submit.prevent="submit" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="password">New Password</label>
                        <input id="password" type="password" class="form-control" v-model="form.password" autocomplete="new-password" required />
                        <div class="form-text">At least 8 characters, with a letter and a number.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="confirm">Confirm Password</label>
                        <input id="confirm" type="password" class="form-control" v-model="form.confirm" autocomplete="new-password" required />
                    </div>

                    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

                    <button class="btn btn-primary w-100" type="submit" :disabled="busy">
                        {{ busy ? 'Saving…' : 'Save new password' }}
                    </button>
                </form>

                <p class="text-center small mt-4 mb-0">
                    <router-link to="/admin/login">← Back to staff sign in</router-link>
                </p>
            </div>
        </div>
    </main>
</template>

<script>
import api from '../../api';
import { errMsg } from '../../util';

export default {
    name: 'AdminResetPassword',
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
                await api.post('/api/admin/password/reset', {
                    email: this.email,
                    token: this.token,
                    password: this.form.password,
                    password_confirmation: this.form.confirm,
                });
                this.$store.dispatch('toast', { text: 'Password reset. Please sign in.', variant: 'success' });
                this.$router.replace('/admin/login');
            } catch (e) {
                this.error = errMsg(e, 'Could not reset your password.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>