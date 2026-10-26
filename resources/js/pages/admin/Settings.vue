<template>
    <div>
        <h1 class="h3 mb-4">Settings</h1>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card card-raised">
                    <div class="card-header">Business details</div>
                    <div class="card-body">
                        <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>
                        <form v-else @submit.prevent="save" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Business name</label>
                                <input class="form-control" v-model="form.business_name" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Business address</label>
                                <input class="form-control" v-model="form.business_address" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Business contact</label>
                                <input class="form-control" v-model="form.business_contact" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Facebook page URL</label>
                                <input class="form-control" v-model="form.business_facebook" />
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Delivery note</label>
                                <textarea class="form-control" rows="2" v-model="form.delivery_note"></textarea>
                            </div>

                            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

                            <button class="btn btn-primary" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Save settings' }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-raised mb-4">
                    <div class="card-header">My account</div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Signed in as <strong>{{ adminName }}</strong></p>
                        <form @submit.prevent="changePassword" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Current password</label>
                                <input class="form-control" type="password" autocomplete="current-password" v-model="pw.current_password" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New password</label>
                                <input class="form-control" type="password" autocomplete="new-password" v-model="pw.new_password" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm new password</label>
                                <input class="form-control" type="password" autocomplete="new-password" v-model="pw.confirm" />
                            </div>

                            <div v-if="pwError" class="alert alert-danger py-2">{{ pwError }}</div>

                            <button class="btn btn-primary" type="submit" :disabled="pwBusy">{{ pwBusy ? 'Saving…' : 'Change password' }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card card-raised">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Staff accounts</span>
                        <button class="btn btn-sm btn-outline-secondary" @click="showCreate = !showCreate">
                            {{ showCreate ? 'Cancel' : 'Add staff' }}
                        </button>
                    </div>
                    <div class="card-body">
                        <form v-if="showCreate" class="row g-2 align-items-end mb-4" @submit.prevent="createStaff">
                            <div class="col-md-3">
                                <label class="form-label">Name</label>
                                <input class="form-control" v-model.trim="createForm.admin_name" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" v-model.trim="createForm.email" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Temporary password</label>
                                <input class="form-control" v-model="createForm.password" />
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" type="submit" :disabled="createBusy">{{ createBusy ? 'Creating…' : 'Create' }}</button>
                            </div>
                            <div v-if="createError" class="col-12">
                                <div class="alert alert-danger py-2 mb-0">{{ createError }}</div>
                            </div>
                        </form>

                        <div v-if="staffLoading" class="text-center py-4 text-muted">Loading…</div>
                        <table v-else class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Last login</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in staff" :key="s.admin_id">
                                    <td class="fw-semibold">
                                        {{ s.admin_name }}
                                        <span v-if="s.admin_id === me.admin_id" class="badge text-bg-light ms-1">You</span>
                                    </td>
                                    <td>{{ s.email }}</td>
                                    <td>
                                        <span class="badge" :class="s.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                            {{ s.is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>{{ s.last_login ? fmtDateTime(s.last_login) : '—' }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary me-1" @click="resetStaff(s)">Reset password</button>
                                        <button
                                            v-if="s.admin_id !== me.admin_id"
                                            class="btn btn-sm"
                                            :class="s.is_active ? 'btn-outline-danger' : 'btn-outline-success'"
                                            @click="toggleActive(s)"
                                        >
                                            {{ s.is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!staff.length">
                                    <td colspan="5" class="text-center text-muted py-4">No staff accounts.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import { errMsg, fmtDateTime } from '../../util';

export default {
    name: 'AdminSettings',
    data() {
        return {
            loading: true,
            busy: false,
            error: '',
            form: { business_name: '', business_address: '', business_contact: '', business_facebook: '', delivery_note: '' },

            pw: { current_password: '', new_password: '', confirm: '' },
            pwBusy: false,
            pwError: '',

            staff: [],
            staffLoading: true,
            showCreate: false,
            createForm: { admin_name: '', email: '', password: '' },
            createBusy: false,
            createError: '',
        };
    },
    computed: {
        me() {
            return this.$store.getters.authUser || {};
        },
        adminName() {
            return this.me.admin_name || this.me.email || '';
        },
    },
    async mounted() {
        const r = await api.get('/api/admin/settings');
        this.form = { ...this.form, ...r.data.settings };
        this.loading = false;
        this.loadStaff();
    },
    methods: {
        fmtDateTime,
        async save() {
            this.busy = true;
            this.error = '';
            try {
                await api.patch('/api/admin/settings', { settings: this.form });
                this.$store.dispatch('toast', { text: 'Settings saved.', variant: 'success' });
            } catch (e) {
                this.error = errMsg(e);
            } finally {
                this.busy = false;
            }
        },
        async changePassword() {
            this.pwError = '';
            if (this.pw.new_password !== this.pw.confirm) {
                this.pwError = 'The new passwords do not match.';
                return;
            }
            this.pwBusy = true;
            try {
                await api.post('/api/admin/change-password', {
                    current_password: this.pw.current_password,
                    new_password: this.pw.new_password,
                    new_password_confirmation: this.pw.confirm,
                });
                this.pw = { current_password: '', new_password: '', confirm: '' };
                this.$store.dispatch('toast', { text: 'Password changed.', variant: 'success' });
            } catch (e) {
                this.pwError = errMsg(e, 'Could not change your password.');
            } finally {
                this.pwBusy = false;
            }
        },
        async loadStaff() {
            this.staffLoading = true;
            try {
                const r = await api.get('/api/admin/staff', { params: { per_page: 100 } });
                this.staff = r.data.data;
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not load staff.'), variant: 'error' });
            } finally {
                this.staffLoading = false;
            }
        },
        async createStaff() {
            this.createError = '';
            if (!this.createForm.admin_name || !this.createForm.email || !this.createForm.password) {
                this.createError = 'Name, email, and password are all required.';
                return;
            }
            this.createBusy = true;
            try {
                await api.post('/api/admin/staff', this.createForm);
                this.createForm = { admin_name: '', email: '', password: '' };
                this.showCreate = false;
                this.$store.dispatch('toast', { text: 'Staff account created.', variant: 'success' });
                await this.loadStaff();
            } catch (e) {
                this.createError = errMsg(e, 'Could not create the account.');
            } finally {
                this.createBusy = false;
            }
        },
        async toggleActive(s) {
            try {
                await api.patch(`/api/admin/staff/${s.admin_id}`, { is_active: !s.is_active });
                this.$store.dispatch('toast', { text: s.is_active ? 'Staff disabled.' : 'Staff enabled.', variant: 'success' });
                await this.loadStaff();
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not update the account.'), variant: 'error' });
            }
        },
        async resetStaff(s) {
            if (!window.confirm(`Reset the password for ${s.email}? A temporary password will be emailed.`)) {
                return;
            }
            try {
                const r = await api.post(`/api/admin/staff/${s.admin_id}/reset-password`);
                this.$store.dispatch('toast', { text: r.data.message, variant: 'success' });
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not reset the password.'), variant: 'error' });
            }
        },
    },
};
</script>
