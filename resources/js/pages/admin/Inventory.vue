<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Inventory</h1>
            <button class="btn btn-primary btn-sm" @click="showCreate = !showCreate">
                {{ showCreate ? 'Close' : '+ Add variety' }}
            </button>
        </div>

        <!-- Create -->
        <div v-if="showCreate" class="card mb-4 card-raised">
            <div class="card-header">New variety</div>
            <div class="card-body">
                <form @submit.prevent="createItem" novalidate>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input class="form-control" v-model="form.name" required />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Category</label>
                            <select class="form-select" v-model="form.category">
                                <option>Local</option>
                                <option>Imported</option>
                                <option>Japanese</option>
                                <option>Thailand</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Unit price</label>
                            <input class="form-control" type="number" step="0.01" min="0" v-model.number="form.unit_price" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Wholesale</label>
                            <input class="form-control" type="number" step="0.01" min="0" v-model.number="form.wholesale_price" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Opening stock</label>
                            <input class="form-control" type="number" min="0" v-model.number="form.initial_stock" />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Low-stock alert threshold (sacks)</label>
                            <input class="form-control" style="max-width: 200px" type="number" min="0" v-model.number="form.low_stock_threshold" />
                        </div>
                        <div class="col-12">
                            <div v-if="createError" class="alert alert-danger py-2">{{ createError }}</div>
                            <button class="btn btn-primary" type="submit" :disabled="busyCreate">{{ busyCreate ? 'Saving…' : 'Save variety' }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <form class="card mb-3" @submit.prevent="loadItems()">
            <div class="card-body d-flex gap-2 flex-wrap">
                <input class="form-control" style="max-width: 240px" v-model="filters.q" placeholder="Search name" />
                <select class="form-select" style="max-width: 160px" v-model="filters.category">
                    <option value="">All categories</option>
                    <option>Local</option>
                    <option>Imported</option>
                    <option>Japanese</option>
                    <option>Thailand</option>
                </select>
                <select class="form-select" style="max-width: 170px" v-model="filters.stock_status">
                    <option value="">All stock levels</option>
                    <option value="in">In stock</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </select>
                <div class="form-check align-self-center">
                    <input class="form-check-input" type="checkbox" id="showArchived" v-model="filters.include_archived" />
                    <label class="form-check-label" for="showArchived">Include archived</label>
                </div>
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>
                <table v-else class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Variety</th>
                            <th>Category</th>
                            <th class="text-end">Unit</th>
                            <th class="text-end">Wholesale</th>
                            <th class="text-end">Stock</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.inventory_id">
                            <td class="fw-semibold">{{ item.name }}</td>
                            <td>{{ item.category }}</td>
                            <td class="text-end">{{ money(item.unit_price, true) }}</td>
                            <td class="text-end">{{ money(item.wholesale_price, true) }}</td>
                            <td class="text-end">
                                <div class="fw-semibold">{{ item.stock }} sack{{ item.stock === 1 ? '' : 's' }}</div>
                                <span v-if="item.stock === 0" class="badge text-bg-danger">Out of stock</span>
                                <span v-else-if="item.stock <= item.low_stock_threshold" class="badge text-bg-warning">Low stock</span>
                                <span v-else class="badge text-bg-success">In stock</span>
                            </td>
                            <td>
                                <span class="badge" :class="item.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ item.is_active ? 'Active' : 'Archived' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1" @click="toggleMovements(item.inventory_id)">
                                    {{ openMovements[item.inventory_id] ? 'Hide' : 'Movements' }}
                                </button>
                                <button v-if="item.is_active" class="btn btn-sm btn-outline-secondary me-1" @click="openEdit(item)">Edit</button>
                                <button v-if="item.is_active" class="btn btn-sm btn-outline-secondary me-1" @click="openAdjust(item)">Stock</button>
                                <button v-if="item.is_active" class="btn btn-sm btn-outline-danger me-1" @click="archive(item)">Archive</button>
                                <button v-else class="btn btn-sm btn-outline-success" @click="restore(item)">Restore</button>
                            </td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="7" class="text-center text-muted py-4">Nothing here.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :pagination="pagination" @page="loadItems" />

        <!-- Adjust stock modal -->
        <div
            v-if="adjustTarget"
            class="modal d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            @click.self="closeAdjust"
            @keydown.esc="closeAdjust"
        >
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form @submit.prevent="saveAdjust">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ adjustTarget.name }} — set stock</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="closeAdjust"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label">Current stock</label>
                                    <div class="form-control-plaintext fw-semibold">{{ adjustTarget.stock }}</div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">New stock</label>
                                    <input ref="stockInput" class="form-control" type="number" min="0" max="100000" v-model.number="adjustForm.stock" />
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Change</label>
                                    <div class="form-control-plaintext fw-semibold" :class="stockDelta > 0 ? 'text-success' : (stockDelta < 0 ? 'text-danger' : 'text-muted')">
                                        {{ stockDelta > 0 ? '+' : '' }}{{ stockDelta }}
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Reason (required)</label>
                                    <input class="form-control" v-model="adjustForm.reason" placeholder="e.g. Physical count" required />
                                </div>
                                <div v-if="adjustError" class="col-12">
                                    <div class="alert alert-danger py-2 mb-0">{{ adjustError }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" @click="closeAdjust">Cancel</button>
                            <button class="btn btn-primary" type="submit" :disabled="busyAdjust">{{ busyAdjust ? 'Saving…' : 'Save stock' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="adjustTarget" class="modal-backdrop show"></div>

        <!-- Edit details modal -->
        <div
            v-if="editTarget"
            class="modal d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            @click.self="closeEdit"
            @keydown.esc="closeEdit"
        >
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <form @submit.prevent="saveEdit">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ editTarget.name }}</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="closeEdit"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input ref="editNameInput" class="form-control" v-model.trim="editForm.name" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" v-model="editForm.category">
                                        <option>Local</option>
                                        <option>Imported</option>
                                        <option>Japanese</option>
                                        <option>Thailand</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Unit price</label>
                                    <input class="form-control" type="number" step="0.01" min="0" v-model.number="editForm.unit_price" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Wholesale (required)</label>
                                    <input
                                        class="form-control"
                                        :class="{ 'is-invalid': wholesaleMissing }"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model.number="editForm.wholesale_price"
                                    />
                                    <div v-if="wholesaleMissing" class="invalid-feedback">
                                        Wholesale price is required — you can’t save changes while it’s empty.
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Low-stock threshold</label>
                                    <input class="form-control" type="number" min="0" max="100000" v-model.number="editForm.low_stock_threshold" />
                                </div>
                                <div v-if="editError" class="col-12">
                                    <div class="alert alert-danger py-2 mb-0">{{ editError }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" @click="closeEdit">Cancel</button>
                            <button class="btn btn-primary" type="submit" :disabled="busyEdit">{{ busyEdit ? 'Saving…' : 'Save changes' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="editTarget" class="modal-backdrop show"></div>

        <!-- Movements -->
        <div v-for="item in itemsWithOpenMovements" :key="'m-' + item.inventory_id" class="card mt-2">
            <div class="card-header small">{{ item.name }} — stock movements</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th class="text-end">Qty</th>
                            <th>Reference</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in movements[item.inventory_id]" :key="m.movement_id">
                            <td>{{ fmtDateTime(m.created_at) }}</td>
                            <td>{{ m.move_type }}</td>
                            <td class="text-end" :class="m.qty > 0 ? 'text-success' : 'text-danger'">{{ m.qty > 0 ? '+' + m.qty : m.qty }}</td>
                            <td class="small">{{ m.ref_type }} {{ m.ref_no || '' }}</td>
                            <td class="small">{{ m.reason }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import Pagination from '../../components/Pagination.vue';
import { money, fmtDateTime, errMsg } from '../../util';

export default {
    name: 'AdminInventory',
    components: { Pagination },
    data() {
        return {
            items: [],
            pagination: null,
            loading: true,
            filters: { q: '', category: '', stock_status: '', include_archived: false },
            showCreate: false,
            form: { name: '', category: 'Local', unit_price: null, wholesale_price: null, initial_stock: null, low_stock_threshold: 10 },
            busyCreate: false,
            createError: '',
            adjustTarget: null,
            adjustForm: { stock: 0, reason: '' },
            busyAdjust: false,
            adjustError: '',
            editTarget: null,
            editForm: { name: '', category: 'Local', unit_price: 0, wholesale_price: 0, low_stock_threshold: 10 },
            busyEdit: false,
            editError: '',
            openMovements: {},
            movements: {},
        };
    },
    computed: {
        itemsWithOpenMovements() {
            return this.items.filter((i) => this.openMovements[i.inventory_id]);
        },
        stockDelta() {
            if (!this.adjustTarget) return 0;
            return Number(this.adjustForm.stock || 0) - this.adjustTarget.stock;
        },
        wholesaleMissing() {
            const v = this.editForm.wholesale_price;
            return v === '' || v === null || v === undefined || Number.isNaN(Number(v));
        },
    },
    methods: {
        money,
        fmtDateTime,
        async loadItems(page = 1) {
            this.loading = true;
            try {
                const params = { page };
                if (this.filters.q) params.q = this.filters.q;
                if (this.filters.category) params.category = this.filters.category;
                if (this.filters.stock_status) params.stock_status = this.filters.stock_status;
                if (this.filters.include_archived) params.include_archived = 1;
                const r = await api.get('/api/admin/inventory', { params });
                this.items = r.data.data;
                this.pagination = r.data.pagination;
            } finally {
                this.loading = false;
            }
        },
        async createItem() {
            this.busyCreate = true;
            this.createError = '';
            try {
                await api.post('/api/admin/inventory', {
                    name: this.form.name,
                    category: this.form.category,
                    unit_price: this.form.unit_price || 0,
                    wholesale_price: this.form.wholesale_price || 0,
                    initial_stock: this.form.initial_stock || 0,
                    low_stock_threshold: this.form.low_stock_threshold ?? 10,
                });
                this.$store.dispatch('toast', { text: 'Variety added.', variant: 'success' });
                this.showCreate = false;
                this.form = { name: '', category: 'Local', unit_price: null, wholesale_price: null, initial_stock: null, low_stock_threshold: 10 };
                await this.loadItems();
            } catch (e) {
                this.createError = errMsg(e);
            } finally {
                this.busyCreate = false;
            }
        },
        openAdjust(item) {
            this.editTarget = null;
            this.adjustTarget = item;
            this.adjustForm = { stock: item.stock, reason: '' };
            this.adjustError = '';
            document.body.classList.add('modal-open');
            this.$nextTick(() => {
                if (this.$refs.stockInput) this.$refs.stockInput.focus();
            });
        },
        closeAdjust() {
            this.adjustTarget = null;
            this.adjustForm = { stock: 0, reason: '' };
            this.adjustError = '';
            document.body.classList.remove('modal-open');
        },
        async saveAdjust() {
            if (!this.adjustForm.reason.trim()) {
                this.adjustError = 'A reason is required.';
                return;
            }
            this.busyAdjust = true;
            this.adjustError = '';
            try {
                await api.patch(`/api/admin/inventory/${this.adjustTarget.inventory_id}/stock`, this.adjustForm);
                this.$store.dispatch('toast', { text: 'Stock updated.', variant: 'success' });
                this.closeAdjust();
                await this.loadItems();
            } catch (e) {
                this.adjustError = errMsg(e);
            } finally {
                this.busyAdjust = false;
            }
        },
        openEdit(item) {
            this.adjustTarget = null;
            this.editTarget = item;
            this.editForm = {
                name: item.name,
                category: item.category,
                unit_price: item.unit_price,
                wholesale_price: item.wholesale_price,
                low_stock_threshold: item.low_stock_threshold,
            };
            this.editError = '';
            document.body.classList.add('modal-open');
            this.$nextTick(() => {
                if (this.$refs.editNameInput) this.$refs.editNameInput.focus();
            });
        },
        closeEdit() {
            this.editTarget = null;
            this.editError = '';
            document.body.classList.remove('modal-open');
        },
        async saveEdit() {
            if (!this.editForm.name.trim()) {
                this.editError = 'A name is required.';
                return;
            }
            if (this.wholesaleMissing) {
                this.editError = '';
                return;
            }
            this.busyEdit = true;
            this.editError = '';
            try {
                await api.patch(`/api/admin/inventory/${this.editTarget.inventory_id}`, this.editForm);
                this.$store.dispatch('toast', { text: 'Variety updated.', variant: 'success' });
                this.closeEdit();
                await this.loadItems();
            } catch (e) {
                this.editError = errMsg(e);
            } finally {
                this.busyEdit = false;
            }
        },
        async archive(item) {
            await api.post(`/api/admin/inventory/${item.inventory_id}/archive`);
            this.$store.dispatch('toast', { text: `${item.name} archived.`, variant: 'success' });
            await this.loadItems();
        },
        async restore(item) {
            await api.post(`/api/admin/inventory/${item.inventory_id}/restore`);
            this.$store.dispatch('toast', { text: `${item.name} restored.`, variant: 'success' });
            await this.loadItems();
        },
        async toggleMovements(id) {
            this.$set(this.openMovements, id, !this.openMovements[id]);
            if (this.openMovements[id] && !this.movements[id]) {
                const r = await api.get(`/api/admin/inventory/${id}/movements`, { params: { per_page: 20 } });
                this.$set(this.movements, id, r.data.data);
            }
        },
    },
    beforeDestroy() {
        document.body.classList.remove('modal-open');
    },
    mounted() {
        this.loadItems();
    },
};
</script>