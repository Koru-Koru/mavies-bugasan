<template>
    <main class="container py-5">
        <div class="card card-raised mx-auto" style="max-width: 420px;">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 text-center mb-4">Staff sign in</h1>
                <p class="text-center text-muted small mb-4">Restricted to shop personnel.</p>

                <form @submit.prevent="submit" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" class="form-control" v-model="form.email" autocomplete="username" required />
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input id="password" type="password" class="form-control" v-model="form.password" autocomplete="current-password" required />
                        <div class="d-flex justify-content-end">
                            <router-link to="/admin/forgot-password" class="small mt-1">Forgot password?</router-link>
                        </div>
                    </div>

                    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

                    <button class="btn btn-primary w-100" type="submit" :disabled="busy">
                        {{ busy ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>

                <p class="text-center small mt-4 mb-0">
                    <router-link to="/">← Back to the shop</router-link>
                </p>
            </div>
        </div>
    </main>
</template>

<script>
import { errMsg } from '../util';

export default {
    name: 'AdminLogin',
    data() {
        return { form: { email: '', password: '' }, busy: false, error: '' };
    },
    methods: {
        async submit() {
            this.busy = true;
            this.error = '';
            try {
                await this.$store.dispatch('login', { guard: 'admin', payload: this.form });
                this.$router.push(this.$route.query.redirect || '/admin');
            } catch (e) {
                this.error = errMsg(e, 'Sign in failed.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>