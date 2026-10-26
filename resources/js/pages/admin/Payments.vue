<template>
    <div>
        <h1 class="h3 mb-4">Payments</h1>

        <div class="card card-raised mb-4">
            <div class="card-header">Record a payment</div>
            <div class="card-body">
                <form @submit.prevent="recordPayment" novalidate>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Customer</label>
                            <select class="form-select" v-model="pickedCustomer" @change="onPickCustomer" required>
                                <option :value="null" disabled>Choose…</option>
                                <option v-for="c in customers" :key="c.customer_id" :value="c.customer_id">
                                    {{ c.first_name }} {{ c.last_name }} · {{ c.phone }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount</label>
                            <input class="form-control" type="number" step="0.01" min="0.01" v-model.number="payment.amount"
                                @change="suggestAllocations" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Method</label>
                            <select class="form-select" v-model="payment.method">
                                <option>Cash</option>
                                <option>Bank Transfer</option>
                                <option>GCash</option>
                                <option>Maya</option>
                                <option>Check</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Reference</label>
                            <input class="form-control" v-model="payment.reference_no" placeholder="Optional" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Received on</label>
                            <input class="form-control" type="date" v-model="payment.received_at" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Note</label>
                            <input class="form-control" v-model="payment.note" placeholder="Optional" />
                        </div>
                    </div>

                    <div v-if="allocRows.length" class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Allocate to orders</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="suggestAllocations">
                                Auto-split oldest first
                            </button>
                        </div>
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th class="text-end">Balance</th>
                                    <th class="text-end" style="width: 180px">Allocate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in allocRows" :key="row.order_id">
                                    <td class="fw-semibold">{{ row.order_code }}</td>
                                    <td><span class="badge" :class="'text-bg-' + statusVariant(row.status)">{{ row.status }}</span></td>
                                    <td class="text-end">{{ money(row.balance) }}</td>
                                    <td>
                                        <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0"
                                            v-model.number="row.alloc" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="allocError" class="alert alert-danger py-2">{{ allocError }}</div>
                    </div>

                    <div v-if="recordError" class="alert alert-danger py-2 mt-3">{{ recordError }}</div>

                    <button class="btn btn-primary mt-3" type="submit" :disabled="busy || !pickedCustomer">
                        {{ busy ? 'Recording…' : 'Record payment' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Payments</span>
                <button class="btn btn-sm btn-outline-secondary" @click="loadPayments()">Refresh</button>
            </div>
            <div class="card-body p-0">
                <div v-if="loadingList" class="text-center py-5 text-muted">Loading…</div>
                <table v-else class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Received</th>
                            <th>Method</th>
                            <th>Orders</th>
                            <th class="text-end">Amount</th>
                            <th>Ref</th>
                            <th>Received by</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payments" :key="p.payment_id">
                            <td>
                                {{ p.payment_id }}
                                <span v-if="p.voided_at" class="badge text-bg-secondary">voided</span>
                            </td>
                            <td>{{ fmtDateTime(p.received_at) }}</td>
                            <td>{{ p.method }}</td>
                            <td class="small">
                                <div v-for="a in p.allocations" :key="a.order_id">
                                    {{ a.order_code }} · {{ money(a.amount) }}
                                </div>
                                <span v-if="!p.allocations || !p.allocations.length" class="text-muted">—</span>
                            </td>
                            <td class="text-end">{{ money(p.amount) }}</td>
                            <td class="small">{{ p.reference_no || '—' }}</td>
                            <td class="small">{{ p.received_by_name || '—' }}</td>
                            <td class="text-end">
                                <button v-if="!p.voided_at" class="btn btn-sm btn-outline-danger" @click="voidPayment(p)">Void</button>
                            </td>
                        </tr>
                        <tr v-if="!payments.length">
                            <td colspan="8" class="text-center text-muted py-4">No payments recorded.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :pagination="pagination" @page="loadPayments" />
    </div>
</template>

<script>
import api from '../../api';
import Pagination from '../../components/Pagination.vue';
import { money, fmtDateTime, statusVariant, errMsg } from '../../util';

export default {
    name: 'AdminPayments',
    components: { Pagination },
    data() {
        return {
            customers: [],
            pickedCustomer: null,
            payment: { amount: null, method: 'Cash', reference_no: '', note: '', received_at: '' },
            allocRows: [],
            allocError: '',
            recordError: '',
            busy: false,
            payments: [],
            pagination: null,
            loadingList: true,
        };
    },
    methods: {
        money,
        fmtDateTime,
        statusVariant,
        async loadCustomers() {
            const r = await api.get('/api/admin/customers', { params: { per_page: 100 } });
            this.customers = r.data.data;
        },
        async onPickCustomer() {
            this.allocRows = [];
            this.allocError = '';
            const r = await api.get(`/api/admin/customers/${this.pickedCustomer}`);
            this.allocRows = (r.data.orders || [])
                .filter((o) => o.balance > 0 && o.status !== 'Cancelled')
                .map((o) => ({ order_id: o.order_id, order_code: o.order_code, status: o.status, balance: o.balance, alloc: 0 }));
        },
        async suggestAllocations() {
            if (!this.pickedCustomer || !this.payment.amount) return;
            try {
                const r = await api.get('/api/admin/payments/suggest', {
                    params: { customer_id: this.pickedCustomer, amount: this.payment.amount },
                });
                const suggested = r.data.allocations || [];
                const map = {};
                for (const s of suggested) map[s.order_id] = s.amount;
                for (const row of this.allocRows) row.alloc = map[row.order_id] || 0;
                this.allocError = '';
            } catch (e) {
                this.allocError = errMsg(e);
            }
        },
        async recordPayment() {
            this.busy = true;
            this.recordError = '';
            const allocations = this.allocRows
                .filter((r) => r.alloc > 0)
                .map((r) => ({ order_id: r.order_id, amount: r.alloc }));

            try {
                await api.post('/api/admin/payments', {
                    amount: this.payment.amount,
                    method: this.payment.method,
                    reference_no: this.payment.reference_no || null,
                    note: this.payment.note || null,
                    received_at: this.payment.received_at || null,
                    allocations,
                });
                this.$store.dispatch('toast', { text: 'Payment recorded.', variant: 'success' });
                this.pickedCustomer = null;
                this.payment = { amount: null, method: 'Cash', reference_no: '', note: '', received_at: '' };
                this.allocRows = [];
                await this.loadPayments();
            } catch (e) {
                this.recordError = errMsg(e);
            } finally {
                this.busy = false;
            }
        },
        async voidPayment(p) {
            const reason = window.prompt(`Reason for voiding payment #${p.payment_id}?`);
            if (reason === null) return;
            if (!reason.trim()) {
                this.$store.dispatch('toast', { text: 'A reason is required to void a payment.', variant: 'danger' });
                return;
            }
            try {
                await api.post(`/api/admin/payments/${p.payment_id}/void`, { reason: reason.trim() });
                this.$store.dispatch('toast', { text: 'Payment voided; balances restored.', variant: 'success' });
                await this.loadPayments();
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not void the payment.'), variant: 'danger' });
            }
        },
        async loadPayments(page = 1) {
            this.loadingList = true;
            try {
                const r = await api.get('/api/admin/payments', { params: { page } });
                this.payments = r.data.data;
                this.pagination = r.data.pagination;
            } finally {
                this.loadingList = false;
            }
        },
    },
    mounted() {
        this.loadCustomers();
        this.loadPayments();
    },
};
</script>