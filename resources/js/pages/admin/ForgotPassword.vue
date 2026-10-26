<template>
    <main class="container py-5">
        <div class="card card-raised mx-auto" style="max-width: 420px;">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 text-center mb-4">Forgot password</h1>
                <p class="text-center text-muted small mb-4">Enter your staff email and we'll send a reset link.</p>

                <form @submit.prevent="submit" novalidate>
                    <div class="mb-4">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" class="form-control" v-model="form.email" autocomplete="username" required />
                    </div>

                    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
                    <div v-if="done" class="alert alert-success py-2">{{ done }}</div>

                    <button class="btn btn-primary w-100" type="submit" :disabled="busy">
                        {{ busy ? 'Sending…' : 'Send reset link' }}
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
    name: 'AdminForgotPassword',
    data() {
        return { form: { email: '' }, busy: false, error: '', done: '' };
    },
    methods: {
        async submit() {
            if (!this.form.email) {
                this.error = 'Enter your email address.';
                return;
            }
            this.error = '';
            this.done = '';
            this.busy = true;
            try {
                const r = await api.post('/api/admin/password/forgot', { email: this.form.email });
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