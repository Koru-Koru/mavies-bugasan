<template>
    <div>
    <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <router-link class="btn btn-sm btn-outline-secondary me-2" to="/admin/customers">← Customers</router-link>
                <h1 class="h3 d-inline-block mb-0">{{ customer.first_name }} {{ customer.last_name }}</h1>
                <span class="badge ms-2" :class="customer.blacklisted ? 'text-bg-dark' : 'text-bg-success'">
                    {{ customer.blacklisted ? 'Blacklisted' : 'Active' }}
                </span>
            </div>
            <div class="d-flex gap-2">
                <button v-if="canReset" class="btn btn-sm btn-outline-secondary" :disabled="resetting" @click="resetPassword">
                    {{ resetting ? 'Resetting…' : 'Reset password' }}
                </button>
                <button v-if="!customer.blacklisted" class="btn btn-sm btn-outline-danger" @click="blacklist">Block customer</button>
                <button v-else class="btn btn-sm btn-outline-success" @click="whitelist">Unblock customer</button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">Details</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Phone</dt>
                            <dd class="col-7">{{ customer.phone }}</dd>
                            <dt class="col-5">Email</dt>
                            <dd class="col-7">{{ customer.email || '—' }}</dd>
                            <dt class="col-5">Address</dt>
                            <dd class="col-7">{{ customer.address_city || '—' }}</dd>
                            <dt class="col-5">Walk-in</dt>
                            <dd class="col-7">{{ customer.is_walk_in ? 'Yes' : 'No' }}</dd>
                            <dt class="col-5">Member since</dt>
                            <dd class="col-7">{{ fmtDate(customer.created_at) }}</dd>
                            <dt class="col-5">Block reason</dt>
                            <dd class="col-7">{{ customer.blacklist_reason || '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">Recent orders</div>
                    <div class="card-body p-0">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in orders" :key="o.order_id">
                                    <td class="fw-semibold">{{ o.order_code }}</td>
                                    <td>{{ fmtDate(o.created_at) }}</td>
                                    <td class="text-end">{{ money(o.amount_due) }}</td>
                                    <td class="text-end">{{ money(o.balance) }}</td>
                                    <td><span class="badge" :class="'text-bg-' + statusVariant(o.status)">{{ o.status }}</span></td>
                                    <td class="text-end">
                                        <router-link class="btn btn-sm btn-outline-primary" :to="'/admin/orders/' + o.order_id">Open</router-link>
                                    </td>
                                </tr>
                                <tr v-if="!orders.length">
                                    <td colspan="6" class="text-center text-muted py-4">No orders yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </template>
    </div>
</template>

<script>
import api from '../../api';
import { money, fmtDate, statusVariant, errMsg } from '../../util';

export default {
    name: 'AdminCustomerShow',
    props: { customerId: { type: [String, Number], required: true } },
    data() {
        return { customer: {}, orders: [], loading: true, error: '', resetting: false };
    },
    computed: {
        canReset() {
            return !this.customer.is_walk_in && !!this.customer.email;
        },
    },
    methods: {
        money,
        fmtDate,
        statusVariant,
        async load() {
            this.loading = true;
            this.error = '';
            try {
                const r = await api.get(`/api/admin/customers/${this.customerId}`);
                this.customer = r.data.customer;
                this.orders = r.data.orders;
            } catch (e) {
                this.error = errMsg(e, 'Could not load the customer.');
            } finally {
                this.loading = false;
            }
        },
        async blacklist() {
            const reason = window.prompt('Reason for blocking this customer?');
            if (reason === null) return;
            await api.post(`/api/admin/customers/${this.customerId}/blacklist`, { reason });
            this.$store.dispatch('toast', { text: 'Customer blocked.', variant: 'success' });
            await this.load();
        },
        async whitelist() {
            await api.post(`/api/admin/customers/${this.customerId}/whitelist`);
            this.$store.dispatch('toast', { text: 'Customer unblocked.', variant: 'success' });
            await this.load();
        },
        async resetPassword() {
            if (!window.confirm(`Reset the password for ${this.customer.email}? A temporary password will be emailed.`)) {
                return;
            }
            this.resetting = true;
            try {
                const r = await api.post(`/api/admin/customers/${this.customerId}/reset-password`);
                this.$store.dispatch('toast', { text: r.data.message, variant: 'success' });
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not reset the password.'), variant: 'error' });
            } finally {
                this.resetting = false;
            }
        },
    },
    mounted() {
        this.load();
    },
};
</script>