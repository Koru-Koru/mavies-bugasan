<template>
    <div class="customer-screen">
        <form class="auth" @submit.prevent="submit">
            <div class="auth-brand">
                <svg class="auth-logo" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                    <path d="M60 112 C59 78 57 46 56 20" stroke="#5D8F2F" stroke-width="5" stroke-linecap="round" />
                    <path d="M56 44 C42 38 32 42 30 52" stroke="#8FC348" stroke-width="5" stroke-linecap="round" />
                    <path d="M58 60 C72 54 82 58 84 68" stroke="#8FC348" stroke-width="5" stroke-linecap="round" />
                    <g fill="#A4D65E">
                        <ellipse cx="46" cy="28" rx="7" ry="4.5" transform="rotate(-24 46 28)" />
                        <ellipse cx="70" cy="24" rx="7" ry="4.5" transform="rotate(16 70 24)" />
                    </g>
                </svg>
                <span class="auth-brand-name">Mavies Bugasan</span>
            </div>

            <h2>Sign In to Order</h2>
            <p class="sub">Welcome back. We missed you.</p>

            <div class="field">
                <label class="label" for="identifier">Phone or Email</label>
                <input
                    id="identifier"
                    ref="identifierInput"
                    v-model.trim="form.identifier"
                    class="input"
                    :class="{ 'input-invalid': identifierInvalid }"
                    :aria-invalid="identifierInvalid"
                    type="text"
                    autocomplete="username"
                    placeholder="09171234567 or you@email.com"
                    @blur="touched.identifier = true"
                >
                <p v-if="identifierInvalid" class="field-error">Enter your phone or email.</p>
            </div>

            <div class="field">
                <label class="label" for="password">Password</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    class="input"
                    :class="{ 'input-invalid': passwordInvalid }"
                    :aria-invalid="passwordInvalid"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Your password"
                    @blur="touched.password = true"
                >
                <p v-if="passwordInvalid" class="field-error">Enter your password.</p>
                <router-link class="forgot-link" to="/forgot-password">Forgot password?</router-link>
            </div>

            <div class="field">
                <label class="check">
                    <input v-model="form.remember" type="checkbox">
                    Remember me
                </label>
            </div>
            <div class="field">
                <label class="check" :class="{ 'check-invalid': termsInvalid }">
                    <input ref="termsInput" v-model="form.terms" type="checkbox" @change="touched.terms = true">
                    I accept the terms and conditions
                </label>
                <p v-if="termsInvalid" class="field-error">Please accept the terms and conditions.</p>
            </div>

            <p v-if="error" class="error">{{ error }}</p>

            <button class="btn btn-dark" type="submit" :disabled="busy">
                {{ busy ? 'Signing in…' : 'SIGN IN' }}
            </button>

            <div class="switch">
                No Account? <router-link to="/register">Sign Up Instead</router-link>
            </div>
        </form>
    </div>
</template>

<script>
import { errMsg } from '../util';

export default {
    name: 'SignIn',
    data() {
        return {
            form: { identifier: '', password: '', remember: false, terms: false },
            touched: { identifier: false, password: false, terms: false },
            error: '',
            busy: false,
        };
    },
    computed: {
        identifierInvalid() {
            return this.touched.identifier && !this.form.identifier;
        },
        passwordInvalid() {
            return this.touched.password && !this.form.password;
        },
        termsInvalid() {
            return this.touched.terms && !this.form.terms;
        },
    },
    methods: {
    async submit() {
            this.touched = { identifier: true, password: true, terms: true };

            if (this.identifierInvalid || this.passwordInvalid || this.termsInvalid) {
                this.error = '';

                this.$nextTick(() => {
                    if (this.identifierInvalid) this.$refs.identifierInput.focus();
                    else if (this.passwordInvalid) this.$refs.passwordInput.focus();
                    else if (this.termsInvalid) this.$refs.termsInput.focus();
                });

                return;
            }
            this.error = '';
            this.busy = true;
            try {
                await this.$store.dispatch('login', {
                    guard: 'customer',
                    payload: { identifier: this.form.identifier, password: this.form.password },
                });
                this.$router.replace(this.$route.query.redirect || '/orders');
            } catch (e) {
                this.error = errMsg(e, 'Incorrect phone/email or password.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>