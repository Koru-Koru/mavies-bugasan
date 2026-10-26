<template>
    <div>
        <h1 class="h3 mb-4">Damages</h1>

        <div class="card card-raised mb-4">
            <div class="card-header">Log damage / spoilage</div>
            <div class="card-body">
                <form @submit.prevent="logDamage" class="row g-3" novalidate>
                    <div class="col-md-4">
                        <label class="form-label">Variety</label>
                        <select class="form-select" v-model="form.inventory_id" required>
                            <option :value="null" disabled>Choose…</option>
                            <option v-for="item in items" :key="item.inventory_id" :value="item.inventory_id">
                                {{ item.name }} ({{ item.stock }} in stock)
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sacks</label>
                        <input class="form-control" type="number" min="1" v-model.number="form.sacks" required />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" v-model="form.damage_type">
                            <option>Pest Infestation</option>
                            <option>Water Damage</option>
                            <option>Torn Sack / Spillage</option>
                            <option>Expired / Spoiled</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date logged</label>
                        <input class="form-control" type="date" v-model="form.date_logged" required />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" rows="2" v-model="form.notes"></textarea>
                    </div>
                    <div class="col-12">
                        <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
                        <button class="btn btn-primary" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Log damage' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recent damage logs</div>
            <div class="card-body p-0">
                <div v-if="loadingList" class="text-center py-5 text-muted">Loading…</div>
                <table v-else class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Date</th>
                            <th>Variety</th>
                            <th class="text-end">Sacks</th>
                            <th>Type</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in logs" :key="d.damage_log_id">
                            <td class="fw-semibold">{{ d.damage_code }}</td>
                            <td>{{ fmtDate(d.date_logged) }}</td>
                            <td>{{ d.name || d.inventory_id }}</td>
                            <td class="text-end">{{ d.sacks }}</td>
                            <td>{{ d.damage_type }}</td>
                            <td class="small text-muted">{{ d.notes || '—' }}</td>
                        </tr>
                        <tr v-if="!logs.length">
                            <td colspan="6" class="text-center text-muted py-4">No damage recorded.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :pagination="pagination" @page="loadLogs" />
    </div>
</template>

<script>
import api from '../../api';
import Pagination from '../../components/Pagination.vue';
import { money, fmtDate, errMsg } from '../../util';

export default {
    name: 'AdminDamages',
    components: { Pagination },
    data() {
        return {
            items: [],
            form: { inventory_id: null, sacks: 1, damage_type: 'Other', date_logged: '', notes: '' },
            busy: false,
            error: '',
            logs: [],
            pagination: null,
            loadingList: true,
        };
    },
    methods: {
        money,
        fmtDate,
        async loadItems() {
            const r = await api.get('/api/catalog', { params: { per_page: 100, include_archived: 1 } });
            this.items = r.data.data;
        },
        async logDamage() {
            this.busy = true;
            this.error = '';
            try {
                await api.post('/api/admin/damages', {
                    inventory_id: this.form.inventory_id,
                    sacks: this.form.sacks,
                    damage_type: this.form.damage_type,
                    date_logged: this.form.date_logged,
                    notes: this.form.notes || null,
                });
                this.$store.dispatch('toast', { text: 'Damage logged.', variant: 'success' });
                this.form = { inventory_id: null, sacks: 1, damage_type: 'Other', date_logged: '', notes: '' };
                await this.loadLogs();
                await this.loadItems();
            } catch (e) {
                this.error = errMsg(e);
            } finally {
                this.busy = false;
            }
        },
        async loadLogs(page = 1) {
            this.loadingList = true;
            try {
                const r = await api.get('/api/admin/damages', { params: { page } });
                this.logs = r.data.data;
                this.pagination = r.data.pagination;
            } finally {
                this.loadingList = false;
            }
        },
    },
    mounted() {
        this.loadItems();
        this.loadLogs();
    },
};
</script>