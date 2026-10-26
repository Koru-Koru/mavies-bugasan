<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Customers</h1>
            <button class="btn btn-primary btn-sm" @click="showCreate = !showCreate">
                {{ showCreate ? 'Close' : '+ Walk-in customer' }}
            </button>
        </div>

        <div v-if="showCreate" class="card mb-4 card-raised">
            <div class="card-header">New walk-in customer</div>
            <div class="card-body">
                <form @submit.prevent="createCustomer" class="row g-3" novalidate>
                    <div class="col-md-4">
                        <label class="form-label">First name</label>
                        <input class="form-control" v-model="form.first_name" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last name</label>
                        <input class="form-control" v-model="form.last_name" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input class="form-control" v-model="form.phone" placeholder="0917 123 4567" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City (optional)</label>
                        <input class="form-control" v-model="form.address_city" />
                    </div>
                    <div class="col-12">
                        <div v-if="createError" class="alert alert-danger py-2">{{ createError }}</div>
                        <button class="btn btn-primary" type="submit" :disabled="busyCreate">{{ busyCreate ? 'Saving…' : 'Save' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <form class="card mb-3" @submit.prevent="loadCustomers()">
            <div class="card-body d-flex gap-2 flex-wrap">
                <input class="form-control" style="max-width: 260px" v-model="filters.q" placeholder="Name / phone / email" />
                <select class="form-select" style="max-width: 180px" v-model="filters.blacklisted">
                    <option value="">All customers</option>
                    <option value="false">Active only</option>
                    <option value="true">Blacklisted</option>
                </select>
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>
                <table v-else class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>City</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in customers" :key="c.customer_id">
                            <td class="fw-semibold">{{ c.first_name }} {{ c.last_name }}</td>
                            <td>{{ c.phone }}</td>
                            <td class="small">{{ c.email || '—' }}</td>
                            <td>{{ c.address_city || '—' }}</td>
                            <td>
                                <span class="badge" :class="c.blacklisted ? 'text-bg-dark' : c.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ c.blacklisted ? 'Blacklisted' : c.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <router-link class="btn btn-sm btn-outline-primary" :to="'/admin/customers/' + c.customer_id">View</router-link>
                                <button v-if="!c.blacklisted" class="btn btn-sm btn-outline-danger" @click="blacklist(c)">Block</button>
                                <button v-else class="btn btn-sm btn-outline-success" @click="whitelist(c)">Unblock</button>
                            </td>
                        </tr>
                        <tr v-if="!customers.length">
                            <td colspan="6" class="text-center text-muted py-4">No customers found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :pagination="pagination" @page="loadCustomers" />
    </div>
</template>

<script>
import api from '../../api';
import Pagination from '../../components/Pagination.vue';
import { errMsg } from '../../util';

export default {
    name: 'AdminCustomers',
    components: { Pagination },
    data() {
        return {
            customers: [],
            pagination: null,
            loading: true,
            filters: { q: '', blacklisted: '' },
            showCreate: false,
            form: { first_name: '', last_name: '', phone: '', address_city: '' },
            busyCreate: false,
            createError: '',
        };
    },
    methods: {
        async loadCustomers(page = 1) {
            this.loading = true;
            try {
                const params = { page };
                if (this.filters.q) params.q = this.filters.q;
                if (this.filters.blacklisted !== '') params.blacklisted = this.filters.blacklisted;
                const r = await api.get('/api/admin/customers', { params });
                this.customers = r.data.data;
                this.pagination = r.data.pagination;
            } finally {
                this.loading = false;
            }
        },
        async createCustomer() {
            this.busyCreate = true;
            this.createError = '';
            try {
                await api.post('/api/admin/customers', this.form);
                this.$store.dispatch('toast', { text: 'Walk-in customer added.', variant: 'success' });
                this.showCreate = false;
                this.form = { first_name: '', last_name: '', phone: '', address_city: '' };
                await this.loadCustomers();
            } catch (e) {
                this.createError = errMsg(e);
            } finally {
                this.busyCreate = false;
            }
        },
        async blacklist(c) {
            const reason = window.prompt('Reason for blocking this customer?');
            if (reason === null) return;
            try {
                await api.post(`/api/admin/customers/${c.customer_id}/blacklist`, { reason });
                this.$store.dispatch('toast', { text: 'Customer blocked.', variant: 'success' });
                await this.loadCustomers();
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e), variant: 'danger' });
            }
        },
        async whitelist(c) {
            await api.post(`/api/admin/customers/${c.customer_id}/whitelist`);
            this.$store.dispatch('toast', { text: 'Customer unblocked.', variant: 'success' });
            await this.loadCustomers();
        },
    },
    mounted() {
        this.loadCustomers();
    },
};
</script>