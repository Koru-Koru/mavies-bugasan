<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Orders</h1>
            <button class="btn btn-outline-secondary btn-sm" @click="loadOrders()">Refresh</button>
        </div>

        <form class="card mb-3" @submit.prevent="loadOrders()">
            <div class="card-body d-flex gap-2 flex-wrap">
                <input class="form-control" style="max-width: 240px" v-model="filters.q" placeholder="Code / name / phone" />
                <select class="form-select" style="max-width: 190px" v-model="filters.status">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                </select>
                <select class="form-select" style="max-width: 160px" v-model="filters.payment_status">
                    <option value="">All payment</option>
                    <option>Paid</option>
                    <option>Partial</option>
                    <option>Unpaid</option>
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
                            <th>Code</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in orders" :key="o.order_id">
                            <td class="fw-semibold">{{ o.order_code }}</td>
                            <td>
                                {{ o.customer_name }}
                                <span v-if="o.blacklisted" class="badge text-bg-dark ms-1">blacklisted</span>
                                <div v-if="o.items && o.items.length" class="small text-muted mt-1">
                                    <div v-for="it in o.items" :key="it.order_item_id">
                                        {{ it.name || it.variety_name }}
                                        <span class="text-nowrap">× {{ it.sacks }} sack{{ it.sacks === 1 ? '' : 's' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ fmtDate(o.created_at) }}</td>
                            <td class="text-end">{{ money(o.amount_due) }}</td>
                            <td class="text-end">{{ money(o.balance) }}</td>
                            <td><span class="badge" :class="'text-bg-' + statusVariant(o.status)">{{ o.status }}</span></td>
                            <td><span class="badge" :class="'text-bg-' + paymentVariant(o.payment_status)">{{ o.payment_status }}</span></td>
                            <td class="text-end">
                                <router-link class="btn btn-sm btn-outline-primary" :to="'/admin/orders/' + o.order_id">Open</router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :pagination="pagination" @page="loadOrders" />
    </div>
</template>

<script>
import api from '../../api';
import Pagination from '../../components/Pagination.vue';
import { money, fmtDate, statusVariant, paymentVariant } from '../../util';

const ORDER_STATUSES = ['Pending', 'Confirmed', 'Ready for Pickup', 'Out for Delivery', 'Delivered', 'Cancelled'];

export default {
    name: 'AdminOrders',
    components: { Pagination },
    data() {
        return {
            statuses: ORDER_STATUSES,
            filters: { q: '', status: '', payment_status: '' },
            orders: [],
            pagination: null,
            loading: true,
        };
    },
    methods: {
        money,
        fmtDate,
        statusVariant,
        paymentVariant,
        async loadOrders(page = 1) {
            this.loading = true;
            try {
                const params = { page };
                if (this.filters.q) params.q = this.filters.q;
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.payment_status) params.payment_status = this.filters.payment_status;
                const r = await api.get('/api/admin/orders', { params });
                this.orders = r.data.data;
                this.pagination = r.data.pagination;
            } finally {
                this.loading = false;
            }
        },
    },
    mounted() {
        this.loadOrders();
    },
};
</script>