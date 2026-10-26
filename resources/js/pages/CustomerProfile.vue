<template>
    <div class="customer-screen">
        <div class="profile-head">
            <button class="back-btn" @click="$router.push('/orders')" aria-label="Back">‹</button>
            <h1>Profile Settings</h1>
        </div>

        <div class="profile-body">
            <div class="avatar-big">{{ initial }}</div>
            <h2 style="font-size:20px;margin:0">{{ form.first_name }} {{ form.last_name }}</h2>
            <p class="hint">{{ form.customer_type }} Customer</p>

            <div class="section-title">Contact</div>
            <div class="field">
                <label class="label">Mobile Number</label>
                <div class="with-edit">
                    <input v-if="editPhone" v-model.trim="form.phone" class="input" type="tel">
                    <span v-else>{{ form.phone || '—' }}</span>
                    <button class="edit-btn" @click="editPhone = !editPhone">{{ editPhone ? 'Done' : 'Edit' }}</button>
                </div>
            </div>
            <div class="field">
                <label class="label">Email</label>
                <div class="with-edit">
                    <input v-if="editEmail" v-model.trim="form.email" class="input" type="email">
                    <span v-else>{{ form.email || '—' }}</span>
                    <button class="edit-btn" @click="editEmail = !editEmail">{{ editEmail ? 'Done' : 'Edit' }}</button>
                </div>
            </div>

            <div class="section-title">Address</div>
            <div class="field">
                <label class="label">House / Street</label>
                <input v-model.trim="form.address_street" class="input" type="text">
            </div>
            <div class="field">
                <label class="label">Barangay</label>
                <input v-model.trim="form.address_brgy" class="input" type="text">
            </div>
            <div class="field">
                <label class="label">City / Municipality</label>
                <input v-model.trim="form.address_city" class="input" type="text">
            </div>
            <div class="field">
                <label class="label">Building / Unit</label>
                <input v-model.trim="form.address_bldg" class="input" type="text">
            </div>

            <p v-if="profileError" class="error">{{ profileError }}</p>
            <button class="btn btn-dark" :disabled="saving" @click="saveProfile">
                {{ saving ? 'Saving…' : 'SAVE CHANGES' }}
            </button>

            <div class="section-title">Change Password</div>
            <template v-if="showPassword">
                <div class="field">
                    <label class="label">Current Password</label>
                    <input v-model="pw.current_password" class="input" type="password" autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="label">New Password</label>
                    <input v-model="pw.new_password" class="input" type="password" autocomplete="new-password">
                </div>
                <div class="field">
                    <label class="label">Confirm New Password</label>
                    <input v-model="pw.new_password_confirmation" class="input" type="password" autocomplete="new-password">
                </div>
                <p v-if="pwError" class="error">{{ pwError }}</p>
                <button class="btn btn-green" :disabled="changingPassword" @click="changePassword">
                    {{ changingPassword ? 'Updating…' : 'UPDATE PASSWORD' }}
                </button>
            </template>
            <button v-else class="btn btn-gray" @click="showPassword = true">CHANGE PASSWORD</button>

            <button class="btn btn-gray" style="margin-top:22px" @click="signOut">SIGN OUT</button>
        </div>
    </div>
</template>

<script>
import api from '../api';
import { errMsg } from '../util';

export default {
    name: 'CustomerProfile',
    data() {
        const u = this.$store.getters.authUser || {};
        return {
            form: {
                first_name: u.first_name || '',
                last_name: u.last_name || '',
                phone: u.phone || '',
                email: u.email || '',
                customer_type: u.customer_type || 'New',
                address_street: u.address_street || '',
                address_brgy: u.address_brgy || '',
                address_city: u.address_city || '',
                address_bldg: u.address_bldg || '',
            },
            editPhone: false,
            editEmail: false,
            saving: false,
            profileError: '',
            showPassword: false,
            changingPassword: false,
            pwError: '',
            pw: { current_password: '', new_password: '', new_password_confirmation: '' },
        };
    },
    computed: {
        initial() {
            return String(this.form.first_name || '?').charAt(0).toUpperCase();
        },
    },
    methods: {
        async saveProfile() {
            this.profileError = '';
            this.saving = true;
            try {
                const r = await api.patch('/api/customer/profile', this.form);
                this.$store.commit('SET_AUTH', { guard: 'customer', user: r.data.customer });
                this.editPhone = false;
                this.editEmail = false;
                this.$store.dispatch('toast', { text: 'Profile updated.' });
            } catch (e) {
                this.profileError = errMsg(e, 'Could not update your profile.');
            } finally {
                this.saving = false;
            }
        },
        async changePassword() {
            this.pwError = '';
            if (!this.pw.current_password || !this.pw.new_password) {
                this.pwError = 'Fill in your current and new password.';
                return;
            }
            if (this.pw.new_password !== this.pw.new_password_confirmation) {
                this.pwError = 'New passwords do not match.';
                return;
            }
            this.changingPassword = true;
            try {
                await api.post('/api/customer/change-password', this.pw);
                this.pw = { current_password: '', new_password: '', new_password_confirmation: '' };
                this.showPassword = false;
                this.$store.dispatch('toast', { text: 'Password changed.' });
            } catch (e) {
                this.pwError = errMsg(e, 'Could not change your password.');
            } finally {
                this.changingPassword = false;
            }
        },
        async signOut() {
            await this.$store.dispatch('logout', 'customer');
            this.$router.replace('/login');
        },
    },
};
</script>