<template>
    <div>
    <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <router-link class="btn btn-sm btn-outline-secondary me-2" to="/admin/orders">← Orders</router-link>
                <h1 class="h3 d-inline-block mb-0">{{ order.order_code }}</h1>
                <span class="badge ms-2" :class="'text-bg-' + statusVariant(order.status)">{{ order.status }}</span>
                <span class="badge ms-1" :class="'text-bg-' + paymentVariant(order.payment_status)">{{ order.payment_status }}</span>
            </div>
            <span class="text-muted small">Placed {{ fmtDateTime(order.created_at) }}</span>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <!-- Items -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Items &amp; pricing</span>
                        <button class="btn btn-sm btn-outline-secondary" @click="editPrices = !editPrices">
                            {{ editPrices ? 'Done' : 'Set prices' }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Variety</th>
                                    <th class="text-end">Sacks</th>
                                    <th class="text-end">Unit price</th>
                                    <th v-if="editPrices" class="text-end">New price</th>
                                    <th class="text-end">Line total</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="it in order.items" :key="it.order_item_id">
                                    <td class="fw-semibold">{{ it.name || it.variety_name }}</td>
                                    <td class="text-end">{{ it.sacks }}</td>
                                    <td class="text-end">
                                        {{ it.unit_price > 0 ? money(it.unit_price) : 'not set' }}
                                    </td>
                                    <td v-if="editPrices" class="text-end">
                                        <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0"
                                            v-model.number="priceInputs[it.order_item_id]" />
                                    </td>
                                    <td class="text-end">{{ money(it.line_total) }}</td>
                                    <td class="small text-muted">{{ it.staff_note || '' }}</td>
                                </tr>
                                <tr v-if="!order.items || !order.items.length">
                                    <td :colspan="editPrices ? 6 : 5" class="text-center text-muted py-3">No items on this order.</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="order.items && order.items.length" class="table-light">
                                <tr>
                                    <td class="fw-semibold">{{ order.items.length }} item{{ order.items.length === 1 ? '' : 's' }}</td>
                                    <td class="text-end fw-semibold">{{ order.total_sacks }}</td>
                                    <td></td>
                                    <td v-if="editPrices"></td>
                                    <td class="text-end fw-semibold">{{ money(itemsSubtotal) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div v-if="editPrices" class="card-body border-top d-flex gap-2 align-items-center flex-wrap">
                        <label class="form-label mb-0">Confirmed total</label>
                        <input class="form-control" style="max-width: 180px" type="number" step="0.01" min="0"
                            v-model.number="confirmedTotal" />
                        <button class="btn btn-primary btn-sm" @click="savePrices">Save prices</button>
                    </div>
                </div>

                <!-- Status -->
                <div class="card mb-4">
                    <div class="card-header">Status</div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Move to</label>
                                <select class="form-select" v-model="nextStatus">
                                    <option value="" disabled>Choose…</option>
                                    <option v-for="s in allowedNext" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reason / note</label>
                                <input class="form-control" v-model="statusNote" :placeholder="reasonHint" />
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" @click="changeStatus">Update</button>
                            </div>
                        </div>
                        <p v-if="errorStatus" class="alert alert-danger py-2 mt-3 mb-0">{{ errorStatus }}</p>
                    </div>
                </div>

                <!-- Courier -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Courier tracking</span>
                        <button class="btn btn-sm btn-outline-secondary" @click="courierOpen = !courierOpen">
                            {{ courierOpen ? 'Done' : (order.courier_link ? 'Edit link' : 'Add link') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <template v-if="!courierOpen">
                            <p v-if="order.courier_link" class="mb-0">
                                <a :href="order.courier_link" target="_blank" rel="noopener">
                                    {{ order.courier_name || 'Courier tracking link' }}
                                </a>
                            </p>
                            <p v-else class="text-muted mb-0">
                                No courier link yet. Add one so the customer can track the delivery.
                            </p>
                        </template>

                        <form v-else @submit.prevent="saveCourier">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Courier</label>
                                    <input class="form-control form-control-sm" v-model.trim="courierForm.name" placeholder="e.g. LBC, J&T" maxlength="80" />
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small mb-1">Tracking link</label>
                                    <input class="form-control form-control-sm" type="url" v-model.trim="courierForm.link" placeholder="https://…" maxlength="500" />
                                </div>
                            </div>
                            <p v-if="courierError" class="alert alert-danger py-2 mt-2 mb-0">{{ courierError }}</p>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-primary btn-sm" type="submit" :disabled="courierBusy">
                                    {{ courierBusy ? 'Saving…' : 'Save courier link' }}
                                </button>
                                <button v-if="order.courier_link" class="btn btn-outline-danger btn-sm" type="button" :disabled="courierBusy" @click="removeCourier">
                                    Remove link
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="courierOpen = false">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Note -->
                <div class="card">
                    <div class="card-header">Order note</div>
                    <div class="card-body">
                        <textarea class="form-control mb-2" rows="2" v-model="noteText" placeholder="Staff notes"></textarea>
                        <button class="btn btn-outline-secondary btn-sm" @click="saveNote">Save note</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Summary -->
                <div class="card mb-4">
                    <div class="card-header">Summary</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">Customer</dt>
                            <dd class="col-6">{{ order.customer_name }}</dd>
                            <dt class="col-6">Phone</dt>
                            <dd class="col-6">{{ order.phone }}</dd>
                            <dt class="col-6">Method</dt>
                            <dd class="col-6">{{ order.fulfillment_method }}</dd>
                            <dt class="col-6">Payment method</dt>
                            <dd class="col-6">{{ order.payment_method || 'Not chosen' }}</dd>
                            <template v-if="order.payment_proof_url">
                                <dt class="col-6">Payment proof</dt>
                                <dd class="col-6">
                                    <a :href="order.payment_proof_url" target="_blank" rel="noopener">View screenshot</a>
                                </dd>
                                <dd class="col-12 mb-2">
                                    <a :href="order.payment_proof_url" target="_blank" rel="noopener">
                                        <img :src="order.payment_proof_url" alt="Payment proof" class="img-fluid rounded border" style="max-height:180px">
                                    </a>
                                </dd>
                            </template>
                            <dt class="col-6">Total sacks</dt>
                            <dd class="col-6">{{ order.total_sacks }}</dd>
                            <dt class="col-6">Classification</dt>
                            <dd class="col-6">{{ order.classification }}</dd>
                        </dl>
                    </div>
                </div>

                <!-- Payment controls -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Payment</span>
                        <span class="badge" :class="'text-bg-' + paymentVariant(order.payment_status)">{{ order.payment_status }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-6">Amount due</dt>
                            <dd class="col-6 text-end mb-0">{{ money(order.amount_due) }}</dd>
                            <dt class="col-6">Paid</dt>
                            <dd class="col-6 text-end mb-0">{{ money(order.amount_paid) }}</dd>
                            <dt class="col-6 mb-0">Balance</dt>
                            <dd class="col-6 text-end mb-0 fw-semibold">{{ money(order.balance) }}</dd>
                        </dl>

                        <div class="d-flex gap-2 flex-wrap">
                            <button v-if="canPay" class="btn btn-success btn-sm" :disabled="payBusy" @click="markPaid">
                                Mark as paid
                            </button>
                            <button v-if="canPay" class="btn btn-outline-primary btn-sm" :disabled="payBusy" @click="togglePayForm">
                                {{ payFormOpen ? 'Cancel' : 'Record payment' }}
                            </button>
                            <button v-if="canVoid" class="btn btn-outline-danger btn-sm" :disabled="payBusy" @click="markUnpaid">
                                Mark as unpaid
                            </button>
                        </div>

                        <form v-if="payFormOpen" class="border-top mt-3 pt-3" @submit.prevent="submitPayment">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Amount</label>
                                    <input class="form-control form-control-sm" type="number" step="0.01" min="0.01"
                                        v-model.number="payForm.amount" required />
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Method</label>
                                    <select class="form-select form-select-sm" v-model="payForm.method">
                                        <option v-for="m in paymentMethods" :key="m">{{ m }}</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Reference</label>
                                    <input class="form-control form-control-sm" v-model="payForm.reference_no" placeholder="Optional" />
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Received on</label>
                                    <input class="form-control form-control-sm" type="date" v-model="payForm.received_at" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">Note</label>
                                    <input class="form-control form-control-sm" v-model="payForm.note" placeholder="Optional" />
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-primary btn-sm" type="submit" :disabled="payBusy">
                                    {{ payBusy ? 'Saving…' : 'Save payment' }}
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="payFormOpen = false">Cancel</button>
                            </div>
                        </form>

                        <p v-if="payError" class="alert alert-danger py-2 mt-3 mb-0">{{ payError }}</p>
                        <p v-if="order.status === 'Cancelled'" class="text-muted small mt-3 mb-0">
                            This order is cancelled, so payments are disabled.
                        </p>
                    </div>
                </div>

                <!-- Payment history -->
                <div class="card mb-4">
                    <div class="card-header">Payment history</div>
                    <div class="card-body p-0">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in order.allocations" :key="a.allocation_id">
                                    <td class="small">
                                        #{{ a.payment_id }}
                                        <span v-if="a.voided_at" class="badge text-bg-secondary">voided</span>
                                        <div class="text-muted">{{ fmtDateTime(a.received_at) }}</div>
                                        <div v-if="a.reference_no" class="text-muted">Ref {{ a.reference_no }}</div>
                                        <div v-if="a.void_reason" class="text-muted">Void: {{ a.void_reason }}</div>
                                    </td>
                                    <td>
                                        {{ a.method }}
                                        <div v-if="a.received_by_name" class="small text-muted">{{ a.received_by_name }}</div>
                                    </td>
                                    <td class="text-end" :class="{ 'text-decoration-line-through text-muted': a.voided_at }">
                                        {{ money(a.amount) }}
                                    </td>
                                    <td class="text-end">
                                        <button v-if="!a.voided_at" class="btn btn-sm btn-outline-danger" :disabled="payBusy" @click="voidAllocation(a)">
                                            Void
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!order.allocations.length">
                                    <td colspan="4" class="text-center text-muted py-3">No payments recorded.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- History -->
                <div class="card">
                    <div class="card-header">History</div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="h in order.history" :key="h.history_id">
                                    <td class="small">{{ h.from_status || '—' }}</td>
                                    <td class="small fw-semibold">{{ h.to_status }}</td>
                                    <td class="small">{{ h.changed_by_name || h.changed_by || '—' }}</td>
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
import { money, fmtDateTime, statusVariant, paymentVariant, errMsg } from '../../util';

const TRANSITIONS = {
    Pending: ['Confirmed', 'Cancelled'],
    Confirmed: ['Ready for Pickup', 'Out for Delivery', 'Cancelled'],
    'Ready for Pickup': ['Delivered', 'Cancelled'],
    'Out for Delivery': ['Delivered', 'Cancelled'],
    Delivered: [],
    Cancelled: [],
};

const PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'GCash', 'Maya', 'Check', 'Other'];

function today() {
    return new Date().toISOString().slice(0, 10);
}

export default {
    name: 'AdminOrderShow',
    props: { orderId: { type: [String, Number], required: true } },
    data() {
        return {
            order: { items: [], allocations: [], history: [] },
            loading: true,
            error: '',
            editPrices: false,
            priceInputs: {},
            confirmedTotal: null,
            nextStatus: '',
            statusNote: '',
            errorStatus: '',
            noteText: '',
            paymentMethods: PAYMENT_METHODS,
            payFormOpen: false,
            payForm: { amount: null, method: 'Cash', reference_no: '', note: '', received_at: '' },
            payBusy: false,
            payError: '',
            courierOpen: false,
            courierForm: { name: '', link: '' },
            courierBusy: false,
            courierError: '',
        };
    },
    computed: {
        allowedNext() {
            return TRANSITIONS[this.order.status] || [];
        },
        itemsSubtotal() {
            return (this.order.items || []).reduce((sum, it) => sum + (Number(it.line_total) || 0), 0);
        },
        canPay() {
            return this.order.status !== 'Cancelled' && Number(this.order.balance) > 0.005;
        },
        canVoid() {
            return this.order.status !== 'Cancelled'
                && (this.order.allocations || []).some((a) => !a.voided_at);
        },
        reasonHint() {
            return this.nextStatus === 'Cancelled' ? 'Reason required to cancel' : 'Optional note';
        },
    },
    methods: {
        money,
        fmtDateTime,
        statusVariant,
        paymentVariant,
        async load() {
            this.loading = true;
            this.error = '';
            try {
                const r = await api.get(`/api/admin/orders/${this.orderId}`);
                this.order = r.data.order;
                this.order.items = r.data.items;
                this.order.allocations = r.data.allocations;
                this.order.history = r.data.history;
                this.noteText = '';
                this.courierOpen = false;
                this.courierError = '';
                this.courierForm = {
                    name: this.order.courier_name || '',
                    link: this.order.courier_link || '',
                };
                this.$nextTick(this.resetInputs);
            } catch (e) {
                this.error = errMsg(e, 'Could not load this order.');
            } finally {
                this.loading = false;
            }
        },
        resetInputs() {
            this.priceInputs = {};
            this.confirmedTotal = null;
            this.nextStatus = '';
            this.statusNote = '';
            this.errorStatus = '';
        },
        resetPayForm() {
            this.payForm = {
                amount: null,
                method: this.order.payment_method || 'Cash',
                reference_no: '',
                note: '',
                received_at: today(),
            };
        },
        togglePayForm() {
            this.payError = '';
            if (this.payFormOpen) {
                this.payFormOpen = false;
                return;
            }
            this.resetPayForm();
            if (Number(this.order.balance) > 0) {
                this.payForm.amount = Number(this.order.balance);
            }
            this.payFormOpen = true;
        },
        async submitPayment() {
            const amount = Number(this.payForm.amount);
            if (!(amount > 0)) {
                this.payError = 'Enter an amount greater than zero.';
                return;
            }
            if (await this.recordPayment(amount)) {
                this.payFormOpen = false;
            }
        },
        async markPaid() {
            const amount = Number(this.order.balance);
            if (!(amount > 0)) return;
            if (!window.confirm(`Record ${this.money(amount)} as paid for ${this.order.order_code}?`)) return;
            this.payError = '';
            this.resetPayForm();
            this.payForm.amount = amount;
            if (await this.recordPayment(amount)) {
                this.payFormOpen = false;
            }
        },
        async markUnpaid() {
            const ids = [...new Set((this.order.allocations || []).filter((a) => !a.voided_at).map((a) => a.payment_id))];
            if (!ids.length) return;

            const warning = ids.length > 1
                ? `This will void ${ids.length} payments. If a payment also covers other orders, those balances will reopen too. Continue?`
                : 'This will void the payment. If it also covers other orders, those balances will reopen. Continue?';
            if (!window.confirm(warning)) return;

            this.payBusy = true;
            this.payError = '';
            try {
                for (const id of ids) {
                    await api.post(`/api/admin/payments/${id}/void`, {
                        reason: `Marked unpaid by admin (${this.order.order_code})`,
                    });
                }
                this.$store.dispatch('toast', { text: 'Payment voided. Balance restored.', variant: 'success' });
                await this.load();
            } catch (e) {
                this.payError = errMsg(e, 'Could not void the payment.');
            } finally {
                this.payBusy = false;
            }
        },
        async voidAllocation(a) {
            const reason = window.prompt(`Reason for voiding payment #${a.payment_id}?`);
            if (reason === null) return;
            if (!reason.trim()) {
                this.payError = 'A reason is required to void a payment.';
                return;
            }

            this.payBusy = true;
            this.payError = '';
            try {
                await api.post(`/api/admin/payments/${a.payment_id}/void`, { reason: reason.trim() });
                this.$store.dispatch('toast', { text: 'Payment voided. Balance restored.', variant: 'success' });
                await this.load();
            } catch (e) {
                this.payError = errMsg(e, 'Could not void the payment.');
            } finally {
                this.payBusy = false;
            }
        },
        async recordPayment(amount) {
            this.payBusy = true;
            this.payError = '';
            try {
                await api.post('/api/admin/payments', {
                    amount,
                    method: this.payForm.method || 'Cash',
                    reference_no: this.payForm.reference_no || null,
                    note: this.payForm.note || null,
                    received_at: this.payForm.received_at || null,
                    allocations: [{ order_id: Number(this.order.order_id), amount }],
                });
                this.$store.dispatch('toast', { text: `Payment of ${this.money(amount)} recorded.`, variant: 'success' });
                this.resetPayForm();
                await this.load();
                return true;
            } catch (e) {
                this.payError = errMsg(e, 'Could not record the payment.');
                return false;
            } finally {
                this.payBusy = false;
            }
        },
        async savePrices() {
            const items = {};
            for (const it of this.order.items) {
                const v = this.priceInputs[it.order_item_id];
                if (typeof v === 'number' && v >= 0) items[it.order_item_id] = v;
            }
            try {
                await api.patch(`/api/admin/orders/${this.orderId}/price`, {
                    items,
                    confirmed_total: this.confirmedTotal === null ? null : this.confirmedTotal,
                });
                this.$store.dispatch('toast', { text: 'Prices saved.', variant: 'success' });
                this.editPrices = false;
                await this.load();
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e), variant: 'danger' });
            }
        },
        async changeStatus() {
            if (!this.nextStatus) return;
            try {
                await api.patch(`/api/admin/orders/${this.orderId}/status`, {
                    status: this.nextStatus,
                    reason: this.statusNote || null,
                    note: this.statusNote || null,
                });
                this.$store.dispatch('toast', { text: `Order moved to ${this.nextStatus}.`, variant: 'success' });
                await this.load();
            } catch (e) {
                this.errorStatus = errMsg(e, 'Could not change status.');
            }
        },
        async saveNote() {
            try {
                await api.patch(`/api/admin/orders/${this.orderId}/note`, { staff_notes: this.noteText || null });
                this.$store.dispatch('toast', { text: 'Note saved.', variant: 'success' });
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e), variant: 'danger' });
            }
        },
        async saveCourier() {
            this.courierBusy = true;
            this.courierError = '';
            try {
                await api.patch(`/api/admin/orders/${this.orderId}/courier`, {
                    courier_name: this.courierForm.name || null,
                    courier_link: this.courierForm.link || null,
                });
                this.$store.dispatch('toast', { text: 'Courier link saved.', variant: 'success' });
                this.courierOpen = false;
                await this.load();
            } catch (e) {
                this.courierError = errMsg(e, 'Could not save the courier link.');
            } finally {
                this.courierBusy = false;
            }
        },
        async removeCourier() {
            if (!window.confirm('Remove the courier link from this order?')) return;
            this.courierBusy = true;
            this.courierError = '';
            try {
                await api.patch(`/api/admin/orders/${this.orderId}/courier`, {
                    courier_name: null,
                    courier_link: null,
                });
                this.$store.dispatch('toast', { text: 'Courier link removed.', variant: 'success' });
                this.courierOpen = false;
                await this.load();
            } catch (e) {
                this.courierError = errMsg(e, 'Could not remove the courier link.');
            } finally {
                this.courierBusy = false;
            }
        },
    },
    mounted() {
        this.load();
    },
};
</script>