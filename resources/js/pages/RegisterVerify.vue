<template>
    <div class="customer-screen">
        <form class="auth" @submit.prevent="submit">
            <h2>Enter the OTP</h2>
            <p class="sub">
                We sent a 6-digit code to<br>
                <strong>{{ email || 'your email' }}</strong>.
            </p>

            <div class="otp-row">
                <input
                    v-for="(d, i) in digits"
                    :key="i"
                    :ref="'digit'"
                    v-model="digits[i]"
                    class="input otp-digit"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    @input="onInput(i, $event)"
                    @keydown="onKeydown(i, $event)"
                    @paste="onPaste($event)"
                >
            </div>

            <p v-if="devHint" class="hint">Trial code (dev): {{ devHint }}</p>
            <p v-if="error" class="error">{{ error }}</p>

            <button class="btn btn-green" type="submit" :disabled="busy">
                {{ busy ? 'Verifying…' : 'VERIFY EMAIL' }}
            </button>

            <div class="resend">
                Haven't Received? <a @click="resend">Resend</a>
            </div>
            <div class="switch">
                Wrong email? <router-link to="/register">Start over</router-link>
            </div>
        </form>
    </div>
</template>

<script>
import api from '../api';
import { errMsg } from '../util';

export default {
    name: 'RegisterVerify',
    data() {
        return {
            digits: ['', '', '', '', '', ''],
            email: this.$route.query.email || '',
            devHint: this.$route.query.dev_otp || '',
            error: '',
            busy: false,
        };
    },
    computed: {
        code() {
            return this.digits.join('');
        },
    },
    mounted() {
        if (/^\d{6}$/.test(this.devHint)) {
            this.digits = this.devHint.split('');
            return;
        }
        this.focusDigit(0);
    },
    methods: {
        focusDigit(i) {
            const el = this.$refs.digit && this.$refs.digit[i];
            if (el && el.focus) el.focus();
        },
        onInput(i, event) {
            const value = String(event.target.value).replace(/\D/g, '');
            this.digits.splice(i, 1, value.slice(-1));
            if (value && i < 5) this.focusDigit(i + 1);
        },
        onKeydown(i, event) {
            if (event.key === 'Backspace' && !this.digits[i] && i > 0) {
                this.focusDigit(i - 1);
            }
        },
        onPaste(event) {
            const text = (event.clipboardData || window.clipboardData).getData('text');
            const chars = String(text).replace(/\D/g, '').slice(0, 6).split('');
            if (!chars.length) return;
            event.preventDefault();
            chars.forEach((c, idx) => this.digits.splice(idx, 1, c));
            this.focusDigit(Math.min(chars.length, 5));
        },
        async submit() {
            if (this.code.length !== 6) {
                this.error = 'Enter the 6-digit code.';
                return;
            }
            this.error = '';
            this.busy = true;
            try {
                await api.post('/api/customer/otp/verify', { email: this.email, code: this.code });
                this.$router.replace('/register/success');
            } catch (e) {
                this.error = errMsg(e, 'That code is incorrect or has expired.');
            } finally {
                this.busy = false;
            }
        },
        async resend() {
            this.error = '';
            try {
                const r = await api.post('/api/customer/register/otp', { email: this.email });
                if (r.data.dev_otp) {
                    this.devHint = r.data.dev_otp;
                    this.digits = r.data.dev_otp.split('');
                } else {
                    this.digits = ['', '', '', '', '', ''];
                    this.focusDigit(0);
                }
                this.$store.dispatch('toast', { text: r.data.message || 'A new code was sent.' });
            } catch (e) {
                this.error = errMsg(e, 'Could not resend the code.');
            }
        },
    },
};
</script>
