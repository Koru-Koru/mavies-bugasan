<template>
    <div class="customer-screen">
        <!-- Order submitted: full-screen confirmation -->
        <div v-if="submitted" class="confirm">
            <div class="big-check" aria-hidden="true">✅</div>
            <h2>Order Submitted</h2>
            <p class="note">We received your order and the admin will review it shortly.</p>

            <div class="summary-card">
                <div class="with-edit">
                    <span class="hint">Order code</span>
                    <strong class="o-code">{{ submitted.order_code }}</strong>
                </div>
                <div class="with-edit" style="margin-top:8px">
                    <span class="hint">Fulfillment</span>
                    <strong>{{ submitted.fulfillment_method }}</strong>
                </div>
                <div class="with-edit" style="margin-top:8px">
                    <span class="hint">Items</span>
                    <strong>{{ submitted.item_count }}</strong>
                </div>
                <div class="with-edit" style="margin-top:8px">
                    <span class="hint">Total sacks</span>
                    <strong>{{ submitted.total_sacks }}</strong>
                </div>
                <div v-if="submitted.items && submitted.items.length" class="order-items">
                    <div v-for="it in submitted.items" :key="it.order_item_id" class="order-item">
                        <span class="oi-name">{{ it.variety_name }} <span class="oi-qty">× {{ it.sacks }} sack{{ it.sacks === 1 ? '' : 's' }}</span></span>
                        <strong class="oi-total">{{ money(it.line_total) }}</strong>
                    </div>
                </div>
                <div class="with-edit" style="margin-top:8px">
                    <span class="hint">Amount due</span>
                    <strong class="o-price">{{ money(submitted.amount_due) }}</strong>
                </div>
                <div v-if="submitted.payment_method" class="with-edit" style="margin-top:8px">
                    <span class="hint">Payment method</span>
                    <strong>{{ submitted.payment_method }}</strong>
                </div>
                <div v-if="submitted.payment_proof_url" class="with-edit" style="margin-top:8px">
                    <span class="hint">Proof of payment</span>
                    <a class="proof-link" :href="submitted.payment_proof_url" target="_blank" rel="noopener">View screenshot</a>
                </div>
                <p class="note" style="margin-top:12px">Admin may reject your order.</p>
            </div>

            <div class="confirm-actions">
                <button class="btn btn-dark" @click="goViewOrders">GO TO VIEW ORDERS</button>
                <button class="btn btn-gray" @click="clearAll">CLEAR</button>
            </div>
        </div>

        <template v-else>
            <div class="o-hero">
                <div class="o-hero-top">
                    <button class="avatar" @click="$router.push('/profile')">{{ initial }}</button>
                    <div class="greeting">Anong ang<br>hanap mo, {{ firstName }}?</div>
                </div>
                <div class="tabs">
                    <div class="tab" :class="{ active: tab === 'orders' }" @click="tab = 'orders'">View Orders</div>
                    <div class="tab" :class="{ active: tab === 'place' }" @click="tab = 'place'">Place Order</div>
                    <div class="tab" :class="{ active: tab === 'catalog' }" @click="openCatalog">Catalog</div>
                </div>
            </div>

            <div class="o-body">
                <!-- ------------------------------ View Orders -->
                <template v-if="tab === 'orders'">
                    <p v-if="loadingOrders" class="hint">Loading orders…</p>
                    <p v-else-if="!orders.length" class="hint">You have no orders yet. Tap "Place Order" to get started.</p>
                    <div v-for="o in orders" :key="o.order_id" class="card">
                        <div class="o-top">
                            <span class="o-code">{{ o.order_code }}</span>
                            <span class="badge" :class="statusClass(o.status)">{{ statusLabel(o.status) }}</span>
                        </div>
                        <div class="o-date">{{ fmtDate(o.created_at) }}</div>
                        <div v-if="o.status !== 'Cancelled'" class="order-track">
                            <div
                                v-for="s in orderSteps(o)"
                                :key="s.label"
                                class="track-step"
                                :class="{ done: s.done, active: s.active }"
                            >
                                <span class="track-dot"></span>
                                <span class="track-label">{{ s.label }}</span>
                            </div>
                        </div>
                        <p class="o-desc">
                            {{ o.item_count }} item{{ o.item_count === 1 ? '' : 's' }} ·
                            {{ o.total_sacks }} sack{{ o.total_sacks === 1 ? '' : 's' }} for {{ o.fulfillment_method }}
                        </p>
                        <div v-if="o.items && o.items.length" class="order-items">
                            <div v-for="it in o.items" :key="it.order_item_id" class="order-item">
                                <div class="oi-main">
                                    <span class="oi-name">{{ it.variety_name }}</span>
                                    <span class="oi-qty">{{ it.sacks }} sack{{ it.sacks === 1 ? '' : 's' }} × {{ money(it.unit_price) }}</span>
                                    <span v-if="it.staff_note" class="oi-note">{{ it.staff_note }}</span>
                                </div>
                                <strong class="oi-total">{{ money(it.line_total) }}</strong>
                            </div>
                        </div>
                        <div class="o-foot">
                            <span class="o-price">{{ money(o.amount_due) }}</span>
                            <span class="badge" :class="paymentClass(o.payment_status)">{{ o.payment_status }}</span>
                        </div>
                        <div v-if="o.payment_method" class="o-pay">Payment method: {{ o.payment_method }}</div>
                        <a v-if="o.payment_proof_url" class="proof-link" :href="o.payment_proof_url" target="_blank" rel="noopener">View payment proof</a>
                        <div v-if="o.courier_link && o.status !== 'Cancelled'" class="courier-row">
                            <a class="courier-link" :href="o.courier_link" target="_blank" rel="noopener">
                                <span>{{ o.courier_name ? 'Track with ' + o.courier_name : 'Track delivery' }}</span>
                                <span class="courier-arrow">→</span>
                            </a>
                        </div>
                        <button
                            v-if="canCancel(o)"
                            class="btn btn-gray"
                            style="margin-top:10px"
                            :disabled="cancelling === o.order_id"
                            @click="cancelOrder(o)"
                        >
                            {{ cancelling === o.order_id ? 'CANCELLING…' : 'CANCEL ORDER' }}
                        </button>
                    </div>
                    <button v-if="hasMoreOrders" class="btn btn-gray" style="margin-top:14px" @click="loadOrders(true)">LOAD MORE</button>
                </template>

                <!-- ------------------------------ Place Order -->
                <template v-else-if="tab === 'place'">
                    <div class="items-head">
                        <div>
                            <label class="label">Items</label>
                            <p class="hint">Mix as many varieties as you need in one order.</p>
                        </div>
                        <span class="items-count">{{ lines.length }} item{{ lines.length === 1 ? '' : 's' }}</span>
                    </div>

                    <div v-for="(line, i) in lines" :key="i" class="var-line">
                        <div class="var-line-top">
                            <span class="var-line-no">Item {{ i + 1 }}</span>
                            <span v-if="lineSubtotal(line) !== null" class="var-line-total">{{ money(lineSubtotal(line)) }}</span>
                        </div>
                        <div class="field">
                            <label class="label">Choose variety</label>
                            <select v-model="line.inventory_id" @change="clampSacks(line)">
                                <option value="">Choose a variety</option>
                                <option
                                    v-for="v in catalog"
                                    :key="v.inventory_id"
                                    :value="v.inventory_id"
                                    :disabled="v.stock <= 0 || isPickedElsewhere(v.inventory_id, i)"
                                >
                                    {{ v.name }} - {{ money(v.unit_price) }}{{ v.stock <= 0 ? ' (out of stock)' : ' (' + v.stock + ' left)' }}
                                </option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label">Qty in Sack</label>
                            <div class="qty-wrap">
                                <span class="qty-cap" @click="bump(line, -1)">-</span>
                                <input v-model.number="line.sacks" class="input" type="number" min="1" :max="maxSacks(line)" @change="clampSacks(line)">
                                <span class="qty-cap" @click="bump(line, 1)">+</span>
                            </div>
                            <p v-if="stockLeft(line) !== null" class="hint" style="margin-top:4px">
                                {{ stockLeft(line) }} sack{{ stockLeft(line) === 1 ? '' : 's' }} available
                            </p>
                            <p v-if="stockLeft(line) !== null && line.sacks > stockLeft(line)" class="error" style="margin-top:4px">
                                Only {{ stockLeft(line) }} available — lower the quantity.
                            </p>
                        </div>
                        <div class="field">
                            <label class="label">Notes</label>
                            <textarea v-model="line.staff_note" rows="1" placeholder="Optional note for this variety"></textarea>
                        </div>
                        <div v-if="lines.length > 1" class="item-actions">
                            <span class="delete-link" @click="removeLine(i)">REMOVE ITEM</span>
                        </div>
                    </div>

                    <button type="button" class="add-item-btn" :disabled="lines.length >= maxLines" @click="addLine">
                        + ADD ANOTHER ITEM
                    </button>
                    <p v-if="lines.length >= maxLines" class="hint" style="text-align:center">
                        Maximum of {{ maxLines }} items per order.
                    </p>

                    <div class="field">
                        <label class="label">Delivery</label>
                        <div class="delivery">
                            <div class="deliv" :class="{ active: method === 'Pickup' }" @click="method = 'Pickup'">
                                Pickup<small>Collect in store</small>
                            </div>
                            <div class="deliv" :class="{ active: method === 'In-house' }" @click="method = 'In-house'">
                                In-house<small>Own rider</small>
                            </div>
                            <div class="deliv" :class="{ active: method === 'Courier' }" @click="method = 'Courier'">
                                Courier<small>3rd-party</small>
                            </div>
                        </div>
                    </div>

                    <template v-if="method !== 'Pickup'">
                        <p class="hint addr-hint">Delivery address is required for {{ method }} orders.</p>
                        <div class="field">
                            <label class="label">House / Street <span class="req">*</span></label>
                            <input
                                ref="addrStreet"
                                v-model.trim="addr.street"
                                class="input"
                                :class="{ 'input-invalid': addressInvalid.street }"
                                type="text"
                                autocomplete="street-address"
                                @blur="addrTouched.street = true"
                            >
                            <p v-if="addressInvalid.street" class="field-error">Enter the house number and street.</p>
                        </div>
                        <div class="field">
                            <label class="label">Barangay <span class="req">*</span></label>
                            <input
                                ref="addrBrgy"
                                v-model.trim="addr.brgy"
                                class="input"
                                :class="{ 'input-invalid': addressInvalid.brgy }"
                                type="text"
                                @blur="addrTouched.brgy = true"
                            >
                            <p v-if="addressInvalid.brgy" class="field-error">Enter the barangay.</p>
                        </div>
                        <div class="field">
                            <label class="label">City / Municipality <span class="req">*</span></label>
                            <input
                                ref="addrCity"
                                v-model.trim="addr.city"
                                class="input"
                                :class="{ 'input-invalid': addressInvalid.city }"
                                type="text"
                                autocomplete="address-level2"
                                @blur="addrTouched.city = true"
                            >
                            <p v-if="addressInvalid.city" class="field-error">Enter the city or municipality.</p>
                        </div>
                    </template>

                    <div class="field">
                        <label class="label">Order Notes</label>
                        <textarea v-model="staff_notes" rows="2" placeholder="Anything the admin should know"></textarea>
                    </div>

                    <div class="order-total">
                        <div v-if="summaryItems.length" class="ot-items">
                            <div v-for="item in summaryItems" :key="item.inventory_id" class="ot-item">
                                <span>{{ item.name }} × {{ item.sacks }}</span>
                                <strong>{{ money(item.total) }}</strong>
                            </div>
                        </div>
                        <div class="ot-line">
                            <span>Selected sacks</span>
                            <strong>{{ summarySacks }}</strong>
                        </div>
                        <div class="ot-line ot-grand">
                            <span>Total amount</span>
                            <strong class="o-price">{{ money(summaryTotal) }}</strong>
                        </div>
                        <p class="hint">Estimated from catalog prices. The admin may adjust the final total.</p>
                    </div>

                    <div class="field">
                        <label class="label">Payment method</label>
                        <div class="delivery pay-methods">
                            <div
                                v-for="pm in paymentMethods"
                                :key="pm.value"
                                class="deliv"
                                :class="{ active: paymentMethod === pm.value }"
                                @click="setPaymentMethod(pm.value)"
                            >
                                {{ pm.label }}<small>{{ pm.value === 'Cash' ? (method === 'Pickup' ? 'Pay on pickup' : 'Pay on delivery') : pm.hint }}</small>
                            </div>
                        </div>
                    </div>

                    <div v-if="onlinePayment" class="pay-qr">
                        <div class="pay-qr-head">
                            <div class="pay-qr-intro">
                                <strong>Pay with {{ onlinePayment.label }}</strong>
                                <span>Scan the QR code using your {{ onlinePayment.label }} app.</span>
                            </div>
                            <div class="pay-qr-due">
                                <span>Amount to pay</span>
                                <strong>{{ money(summaryTotal) }}</strong>
                            </div>
                        </div>
                        <a :href="onlinePayment.qr" target="_blank" rel="noopener" class="pay-qr-image">
                            <img :src="onlinePayment.qr" :alt="onlinePayment.label + ' payment QR code'">
                        </a>
                        <p class="hint">Tap the QR to open it full size. After paying, upload your receipt below.</p>
                    </div>

                    <div v-if="needsProof" class="field proof-field">
                        <label class="label">Proof of payment <span class="hint">(optional)</span></label>
                        <input
                            ref="proofInput"
                            class="proof-input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="onProofChange"
                        >
                        <div v-if="proofPreview" class="proof-preview">
                            <img :src="proofPreview" alt="Payment proof preview">
                            <button type="button" class="delete-link" @click="removeProof">REMOVE</button>
                        </div>
                        <p class="hint">Upload a screenshot of your {{ paymentMethod }} receipt (JPG, PNG, or WebP, up to 5 MB).</p>
                        <p v-if="proofError" class="error">{{ proofError }}</p>
                    </div>

                    <p v-if="error" class="error">{{ error }}</p>

                    <button class="btn btn-dark" :disabled="placing" @click="placeOrder">
                        {{ placing ? 'Placing…' : 'PLACE ORDER' }}
                    </button>
                    <button class="btn btn-gray" style="margin-top:10px" @click="clearPlace">CLEAR</button>
                </template>

                <!-- ------------------------------ Catalog -->
                <template v-else>
                    <div class="cat-tools">
                        <select v-model="filterCategory" @change="loadCatalog">
                            <option value="">All categories</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <select v-model="sortBy" @change="loadCatalog">
                            <option value="name">Sort: Name</option>
                            <option value="price_asc">Price: Low</option>
                            <option value="price_desc">Price: High</option>
                            <option value="stock">Most stock</option>
                        </select>
                    </div>
                    <p v-if="loadingCatalog" class="hint">Loading catalog…</p>
                    <p v-else-if="!catalog.length" class="hint">No varieties match your filters.</p>
                    <div v-for="c in catalog" :key="c.inventory_id" class="catalog-card">
                        <div class="catalog-info">
                            <h3>{{ c.name }}</h3>
                            <span class="badge blue">{{ c.category }}</span>
                            <div class="stock">{{ c.stock }} sack{{ c.stock === 1 ? '' : 's' }} in stock</div>
                        </div>
                        <div class="catalog-right">
                            <div class="price">{{ money(c.unit_price) }}</div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>

<script>
import api from '../api';
import { money, fmtDate, errMsg } from '../util';

export default {
    name: 'CustomerOrders',
    data() {
        return {
            tab: 'orders',
            loadingOrders: false,
            orders: [],
            orderPage: 1,
            orderPages: 1,
            loadingCatalog: false,
            catalogLoaded: false,
            catalog: [],
            categories: [],
            filterCategory: '',
            sortBy: 'name',
            lines: [this.blankLine()],
            maxLines: 20,
            method: 'Pickup',
            addr: { street: '', brgy: '', city: '' },
            addrTouched: { street: false, brgy: false, city: false },
            staff_notes: '',
            paymentMethod: 'Cash',
            paymentMethods: [
                { value: 'Cash', label: 'Cash', hint: '' },
                { value: 'GCash', label: 'GCash', hint: 'Scan QR', qr: '/images/payment/gcash-qr.jpg' },
                { value: 'Maya', label: 'Maya', hint: 'Scan QR', qr: '/images/payment/maya-qr.jpg' },
            ],
            paymentProof: null,
            proofPreview: '',
            proofError: '',
            error: '',
            placing: false,
            submitted: null,
            cancelling: null,
        };
    },
    computed: {
        firstName() {
            const u = this.$store.getters.authUser || {};
            return u.first_name || 'kaibigan';
        },
        initial() {
            return String(this.firstName).charAt(0).toUpperCase();
        },
        hasMoreOrders() {
            return this.orderPage < this.orderPages;
        },
        needsProof() {
            return this.paymentMethod === 'GCash' || this.paymentMethod === 'Maya';
        },
        addressInvalid() {
            if (this.method === 'Pickup') {
                return { street: false, brgy: false, city: false };
            }

            return {
                street: this.addrTouched.street && !this.addr.street,
                brgy: this.addrTouched.brgy && !this.addr.brgy,
                city: this.addrTouched.city && !this.addr.city,
            };
        },
        onlinePayment() {
            return this.paymentMethods.find((pm) => pm.value === this.paymentMethod && pm.qr) || null;
        },
        summaryItems() {
            return this.lines.reduce((items, line) => {
                if (!line.inventory_id) return items;
                const item = this.catalog.find((v) => Number(v.inventory_id) === Number(line.inventory_id));
                if (!item) return items;
                const sacks = Number(line.sacks) || 0;
                items.push({
                    inventory_id: item.inventory_id,
                    name: item.name,
                    sacks,
                    total: (Number(item.unit_price) || 0) * sacks,
                });
                return items;
            }, []);
        },
        summarySacks() {
            return this.summaryItems.reduce((total, item) => total + item.sacks, 0);
        },
        summaryTotal() {
            return this.summaryItems.reduce((total, item) => total + item.total, 0);
        },
    },
    mounted() {
        this.loadOrders();
        this.loadCatalog();
    },
    beforeDestroy() {
        this.revokeProofPreview();
    },
    methods: {
        money,
        fmtDate,
        blankLine() {
            return { inventory_id: '', sacks: 1, staff_note: '' };
        },
        stockLeft(line) {
            const item = this.catalog.find((v) => Number(v.inventory_id) === Number(line.inventory_id));
            return item ? Number(item.stock) : null;
        },
        lineSubtotal(line) {
            if (!line.inventory_id) return null;
            const item = this.catalog.find((v) => Number(v.inventory_id) === Number(line.inventory_id));
            if (!item) return null;
            return (Number(item.unit_price) || 0) * (Number(line.sacks) || 0);
        },
        isPickedElsewhere(inventoryId, index) {
            return this.lines.some((line, i) => i !== index && Number(line.inventory_id) === Number(inventoryId));
        },
        maxSacks(line) {
            const left = this.stockLeft(line);
            if (left === null) return 500;
            return Math.min(500, Math.max(1, left));
        },
        clampSacks(line) {
            const max = this.maxSacks(line);
            const value = Number(line.sacks);
            if (!Number.isFinite(value) || value < 1) {
                line.sacks = 1;
            } else if (value > max) {
                line.sacks = max;
            }
        },
        statusClass(status) {
            return {
                Pending: 'amber',
                Confirmed: 'blue',
                'Ready for Pickup': 'blue',
                'Out for Delivery': 'blue',
                Delivered: 'green',
                Cancelled: 'red',
            }[status] || 'blue';
        },
        statusLabel(status) {
            return {
                Pending: 'Order received',
                Confirmed: 'Preparing your order',
                'Ready for Pickup': 'Ready for pickup',
                'Out for Delivery': 'To deliver',
                Delivered: 'Delivered',
                Cancelled: 'Cancelled',
            }[status] || status;
        },
        orderSteps(o) {
            const labels = o.fulfillment_method === 'Pickup'
                ? ['Received', 'Preparing', 'Ready for pickup', 'Picked up']
                : ['Received', 'Preparing', 'To deliver', 'Delivered'];
            const stages = {
                Pending: 0,
                Confirmed: 1,
                'Ready for Pickup': 2,
                'Out for Delivery': 2,
                Delivered: 3,
            };
            const at = stages[o.status];
            const current = at === undefined ? -1 : at;

            return labels.map((label, i) => ({
                label,
                done: i < current,
                active: i === current,
            }));
        },
        paymentClass(status) {
            return { Paid: 'green', Partial: 'amber', Unpaid: 'red' }[status] || 'amber';
        },
        canCancel(o) {
            return ['Pending', 'Confirmed', 'Ready for Pickup', 'Out for Delivery'].includes(o.status);
        },
        async cancelOrder(o) {
            if (!window.confirm(`Cancel order ${o.order_code}? The reserved stock will be restored.`)) {
                return;
            }
            this.cancelling = o.order_id;
            try {
                await api.post(`/api/customer/orders/${o.order_id}/cancel`);
                this.$store.dispatch('toast', { text: `Order ${o.order_code} cancelled.`, variant: 'success' });
                await this.loadOrders();
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not cancel the order.'), variant: 'error' });
            } finally {
                this.cancelling = null;
            }
        },
        async loadOrders(more = false) {
            this.loadingOrders = true;
            this.orderPage = more ? this.orderPage + 1 : 1;
            try {
                const r = await api.get('/api/customer/orders', { params: { page: this.orderPage } });
                this.orders = more ? this.orders.concat(r.data.data) : r.data.data;
                this.orderPages = r.data.pagination.pages;
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not load your orders.'), variant: 'error' });
            } finally {
                this.loadingOrders = false;
            }
        },
        async loadCatalog() {
            this.loadingCatalog = true;
            try {
                const params = { per_page: 100 };
                if (this.filterCategory) params.category = this.filterCategory;
                if (this.sortBy) params.sort = this.sortBy;
                const r = await api.get('/api/catalog', { params });
                this.catalog = r.data.data;
                this.categories = (r.data.facets && r.data.facets.categories) || [];
                this.catalogLoaded = true;
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not load the catalog.'), variant: 'error' });
            } finally {
                this.loadingCatalog = false;
            }
        },
        openCatalog() {
            this.tab = 'catalog';
            if (!this.catalogLoaded) this.loadCatalog();
        },
        addLine() {
            if (this.lines.length >= this.maxLines) return;
            this.lines.push(this.blankLine());
            this.$nextTick(() => {
                const selects = this.$el.querySelectorAll('.var-line select');
                const last = selects[selects.length - 1];
                if (last) last.focus();
            });
        },
        removeLine(i) {
            this.lines.splice(i, 1);
        },
        bump(line, delta) {
            const next = Number(line.sacks || 0) + delta;
            line.sacks = Math.max(1, Math.min(this.maxSacks(line), next));
        },
        setPaymentMethod(value) {
            this.paymentMethod = value;
            if (value === 'Cash') {
                this.removeProof();
            }
        },
        revokeProofPreview() {
            if (this.proofPreview && this.proofPreview.startsWith('blob:')) {
                URL.revokeObjectURL(this.proofPreview);
            }
            this.proofPreview = '';
        },
        removeProof() {
            this.paymentProof = null;
            this.proofError = '';
            this.revokeProofPreview();
            if (this.$refs.proofInput) {
                this.$refs.proofInput.value = '';
            }
        },
        onProofChange(event) {
            const file = event.target.files && event.target.files[0];
            this.proofError = '';

            if (!file) {
                this.removeProof();
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.removeProof();
                this.proofError = 'Choose a JPG, PNG, or WebP image.';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.removeProof();
                this.proofError = 'The screenshot must be 5 MB or smaller.';
                return;
            }

            this.revokeProofPreview();
            this.paymentProof = file;
            this.proofPreview = URL.createObjectURL(file);
        },
        clearPlace() {
            this.lines = [this.blankLine()];
            this.method = 'Pickup';
            this.addr = { street: '', brgy: '', city: '' };
            this.addrTouched = { street: false, brgy: false, city: false };
            this.staff_notes = '';
            this.paymentMethod = 'Cash';
            this.removeProof();
            this.error = '';
        },
        clearAll() {
            this.clearPlace();
            this.submitted = null;
            this.tab = 'place';
        },
        async placeOrder() {
            const lines = this.lines
                .filter((l) => l.inventory_id !== '' && l.inventory_id !== null)
                .map((l) => ({ inventory_id: Number(l.inventory_id), sacks: Number(l.sacks) || 1, staff_note: l.staff_note || null }));

            if (this.lines.length !== lines.length || !lines.length) {
                this.error = 'Choose a variety on every line before placing the order.';
                return;
            }
            const over = this.lines.find((l) => this.stockLeft(l) !== null && Number(l.sacks) > this.stockLeft(l));
            if (over) {
                const item = this.catalog.find((v) => Number(v.inventory_id) === Number(over.inventory_id));
                this.error = `Only ${this.stockLeft(over)} sack${this.stockLeft(over) === 1 ? '' : 's'} of ${item ? item.name : 'that variety'} available.`;
                return;
            }
            if (this.method !== 'Pickup') {
                this.addrTouched = { street: true, brgy: true, city: true };

                if (!this.addr.street || !this.addr.brgy || !this.addr.city) {
                    this.error = 'Complete the delivery address (house/street, barangay, city).';

                    this.$nextTick(() => {
                        const ref = !this.addr.street ? 'addrStreet' : (!this.addr.brgy ? 'addrBrgy' : 'addrCity');
                        if (this.$refs[ref]) this.$refs[ref].focus();
                    });

                    return;
                }
            }

            this.error = '';
            this.placing = true;
            try {
                const form = new FormData();
                form.append('fulfillment_method', this.method);
                form.append('payment_method', this.paymentMethod);
                if (this.addr.street) form.append('address_street', this.addr.street);
                if (this.addr.brgy) form.append('address_brgy', this.addr.brgy);
                if (this.addr.city) form.append('address_city', this.addr.city);
                if (this.staff_notes) form.append('staff_notes', this.staff_notes);
                lines.forEach((line, i) => {
                    form.append(`lines[${i}][inventory_id]`, line.inventory_id);
                    form.append(`lines[${i}][sacks]`, line.sacks);
                    if (line.staff_note) form.append(`lines[${i}][staff_note]`, line.staff_note);
                });
                if (this.paymentProof) form.append('payment_proof', this.paymentProof);

                const r = await api.post('/api/customer/orders', form);
                this.submitted = r.data.order;
                this.removeProof();
            } catch (e) {
                this.error = errMsg(e, 'Could not place your order.');
            } finally {
                this.placing = false;
            }
        },
        goViewOrders() {
            this.clearPlace();
            this.submitted = null;
            this.tab = 'orders';
            this.loadOrders();
        },
    },
};
</script>