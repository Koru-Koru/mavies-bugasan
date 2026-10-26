<template>
    <div class="customer-screen">
        <form class="auth" @submit.prevent="next">
            <h2>Create Account</h2>
            <p class="sub">It only takes a minute.</p>

            <div class="steps">
                <span class="step-dot" :class="{ active: step === 1, done: step > 1 }">1</span>
                <span class="step-bar" :class="{ done: step > 1 }"></span>
                <span class="step-dot" :class="{ active: step === 2, done: step > 2 }">2</span>
                <span class="step-bar" :class="{ done: step > 2 }"></span>
                <span class="step-dot" :class="{ active: step === 3 }">3</span>
            </div>

            <template v-if="step === 1">
                <div class="field">
                    <label class="label" for="first_name">First Name</label>
                    <input id="first_name" v-model.trim="form.first_name" class="input" type="text" placeholder="Juan">
                </div>
                <div class="field">
                    <label class="label" for="last_name">Last Name</label>
                    <input id="last_name" v-model.trim="form.last_name" class="input" type="text" placeholder="Dela Cruz">
                </div>
                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" v-model.trim="form.email" class="input" type="email" autocomplete="email" placeholder="you@email.com">
                </div>
                <div class="field">
                    <label class="label" for="phone">Mobile Number</label>
                    <input id="phone" v-model.trim="form.phone" class="input" type="tel" autocomplete="tel" placeholder="09171234567">
                </div>
            </template>

            <template v-else-if="step === 2">
                <div class="field">
                    <label class="label" for="address_street">House / Street</label>
                    <input id="address_street" v-model.trim="form.address_street" class="input" type="text" placeholder="123 Mabini St.">
                </div>
                <div class="field">
                    <label class="label" for="address_brgy">Barangay</label>
                    <input id="address_brgy" v-model.trim="form.address_brgy" class="input" type="text" placeholder="Barangay Poblacion">
                </div>
                <div class="field">
                    <label class="label" for="address_city">City / Municipality</label>
                    <input id="address_city" v-model.trim="form.address_city" class="input" type="text" placeholder="City of San Fernando">
                </div>
                <div class="field">
                    <label class="label" for="address_bldg">Building / Unit (optional)</label>
                    <input id="address_bldg" v-model.trim="form.address_bldg" class="input" type="text" placeholder="Unit 4B">
                </div>
            </template>

            <template v-else>
                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input id="password" v-model="form.password" class="input" type="password" autocomplete="new-password" placeholder="At least 8 characters">
                </div>
                <div class="field">
                    <label class="label" for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" class="input" type="password" autocomplete="new-password" placeholder="Repeat password">
                </div>

                <div class="field">
                    <label class="label">Are you a…</label>
                    <div class="radio-pill">
                        <label :class="{ active: form.customer_type === 'New' }">
                            <input v-model="form.customer_type" type="radio" value="New">
                            New Customer
                        </label>
                        <label :class="{ active: form.customer_type === 'Returning' }">
                            <input v-model="form.customer_type" type="radio" value="Returning">
                            Returning
                        </label>
                    </div>
                </div>

                <div class="field">
                    <label class="check">
                        <input v-model="form.terms_accepted" type="checkbox">
                        I have read and accept the privacy policy and terms of service.
                    </label>
                </div>
            </template>

            <p v-if="error" class="error">{{ error }}</p>

            <button class="btn" :class="step === 3 ? 'btn-green' : 'btn-dark'" type="submit" :disabled="busy">
                {{ submitLabel }}
            </button>

            <div v-if="step > 1" class="switch">
                <a @click="step--">Back</a>
            </div>
            <div v-else class="switch">
                Already have an account? <router-link to="/login">Sign In</router-link>
            </div>
        </form>
    </div>
</template>

<script>
import { errMsg } from '../util';

export default {
    name: 'SignUp',
    data() {
        return {
            step: 1,
            busy: false,
            error: '',
            form: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                address_street: '',
                address_brgy: '',
                address_city: '',
                address_bldg: '',
                password: '',
                password_confirmation: '',
                customer_type: 'New',
                terms_accepted: false,
            },
        };
    },
    computed: {
        submitLabel() {
            if (this.busy) return 'Creating account…';
            return this.step === 3 ? 'CREATE ACCOUNT' : 'CONTINUE';
        },
    },
    methods: {
        validateStep1() {
            if (!this.form.first_name || !this.form.last_name) return 'Enter your first and last name.';
            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.form.email)) return 'Enter a valid email address.';
            if (String(this.form.phone).replace(/\D/g, '').length < 10) return 'Enter a valid mobile number.';
            return '';
        },
        validateStep2() {
            if (!this.form.address_street || !this.form.address_brgy || !this.form.address_city) {
                return 'Complete your address (street, barangay, city).';
            }
            return '';
        },
        validateStep3() {
            if (this.form.password.length < 8) return 'Password must be at least 8 characters.';
            if (this.form.password !== this.form.password_confirmation) return 'Passwords do not match.';
            if (!this.form.terms_accepted) return 'Please accept the terms of service.';
            return '';
        },
        next() {
            if (this.step === 1) {
                this.error = this.validateStep1();
                if (this.error) return;
                this.step = 2;
                return;
            }
            if (this.step === 2) {
                this.error = this.validateStep2();
                if (this.error) return;
                this.step = 3;
                return;
            }
            this.submit();
        },
        async submit() {
            this.error = this.validateStep3();
            if (this.error) return;

            this.busy = true;
            try {
                const data = await this.$store.dispatch('register', { ...this.form, terms_accepted: true });
                this.$router.replace({
                    path: '/register/verify',
                    query: { email: data.customer.email, dev_otp: data.dev_otp || undefined },
                });
            } catch (e) {
                this.error = errMsg(e, 'Could not create your account.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>