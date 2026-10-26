<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Reports</h1>
            <div class="btn-group">
                <a class="btn btn-sm btn-outline-secondary" :href="exportUrl('csv')">Export CSV</a>
                <a class="btn btn-sm btn-outline-secondary" :href="exportUrl('pdf')">Export PDF</a>
            </div>
        </div>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item" v-for="t in tabs" :key="t.key">
                <a class="nav-link" :class="{ active: report === t.key }" href="#" @click.prevent="select(t.key)">{{ t.label }}</a>
            </li>
        </ul>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4" v-if="report === 'variety'">
                        <label class="form-label">Variety</label>
                        <select class="form-select" v-model="filters.inventory_id" @change="load">
                            <option value="">Select a variety…</option>
                            <option v-for="v in varieties" :key="v.inventory_id" :value="v.inventory_id">
                                {{ v.name }} — {{ v.category }}{{ v.is_active ? '' : ' (archived)' }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3" v-if="hasRange">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" v-model="filters.from" @change="load" />
                    </div>
                    <div class="col-md-3" v-if="hasRange">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" v-model="filters.to" @change="load" />
                    </div>
                    <div class="col-md-3" v-if="hasBasis">
                        <label class="form-label">Basis</label>
                        <select class="form-select" v-model="filters.basis" @change="load">
                            <option value="ordered">As ordered</option>
                            <option value="delivered">As delivered</option>
                        </select>
                    </div>
                    <div class="col-md-3" v-if="report === 'inventory'">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="inc-arch" v-model="filters.include_archived" @change="load" />
                            <label class="form-check-label" for="inc-arch">Include archived varieties</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>

        <template v-else-if="data">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-4 col-xl-2" v-for="m in data.metrics" :key="m.label">
                    <div class="card card-raised h-100">
                        <div class="card-body py-3">
                            <div class="text-uppercase text-muted" style="font-size: .7rem; letter-spacing: .04em;">{{ m.label }}</div>
                            <div class="fw-bold" :class="toneClass(m.tone)" style="font-size: 1.15rem;">{{ fmtCell(m.value, m.format) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ data.title }}</span>
                    <span class="text-muted small">{{ data.subtitle }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th v-for="c in data.columns" :key="c.key" :class="c.align === 'right' ? 'text-end' : ''">{{ c.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in data.rows" :key="i">
                                <td v-for="c in data.columns" :key="c.key" :class="c.align === 'right' ? 'text-end' : ''">
                                    {{ fmtCell(row[c.key], c.format) }}
                                </td>
                            </tr>
                            <tr v-if="!data.rows.length">
                                <td :colspan="data.columns.length" class="text-center text-muted py-4">{{ data.empty || 'No records for this period.' }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="data.totals">
                            <tr class="table-light fw-bold">
                                <td v-for="(c, i) in data.columns" :key="c.key" :class="c.align === 'right' ? 'text-end' : ''">
                                    <template v-if="i === 0">TOTAL</template>
                                    <template v-else-if="hasTotal(c.key)">{{ fmtCell(data.totals[c.key], c.format) }}</template>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import api from '../../api';
import { money, fmtDate, errMsg } from '../../util';

export default {
    name: 'AdminReports',
    data() {
        return {
            report: 'summary',
            loading: true,
            data: null,
            varieties: [],
            filters: { from: '', to: '', basis: 'ordered', include_archived: false, inventory_id: '' },
            tabs: [
                { key: 'summary', label: 'Summary' },
                { key: 'top-varieties', label: 'Top varieties' },
                { key: 'variety', label: 'Variety sales' },
                { key: 'by-day', label: 'By day' },
                { key: 'inventory', label: 'Inventory' },
                { key: 'damage', label: 'Damage' },
                { key: 'credit', label: 'Credit' },
            ],
        };
    },
    computed: {
        hasRange() {
            return ['summary', 'top-varieties', 'variety', 'by-day', 'damage'].includes(this.report);
        },
        hasBasis() {
            return ['summary', 'top-varieties', 'variety', 'by-day'].includes(this.report);
        },
    },
    methods: {
        hasTotal(key) {
            return this.data && this.data.totals && Object.prototype.hasOwnProperty.call(this.data.totals, key);
        },
        toneClass(tone) {
            return { warning: 'text-warning', muted: 'text-muted' }[tone] || '';
        },
        fmtCell(value, format) {
            if (value === null || value === undefined || value === '') return '—';
            if (format === 'money') return money(value);
            if (format === 'int') return Number(value).toLocaleString('en-US');
            return String(value);
        },
        query() {
            const params = new URLSearchParams();
            params.set('report', this.report);
            if (this.hasRange) {
                if (this.filters.from) params.set('from', this.filters.from);
                if (this.filters.to) params.set('to', this.filters.to);
            }
            if (this.hasBasis) params.set('basis', this.filters.basis);
            if (this.report === 'variety' && this.filters.inventory_id) {
                params.set('inventory_id', this.filters.inventory_id);
            }
            if (this.report === 'inventory' && this.filters.include_archived) params.set('include_archived', '1');
            return params;
        },
        exportUrl(format) {
            const params = this.query();
            params.set('format', format);
            return '/api/admin/reports/export?' + params.toString();
        },
        select(key) {
            this.report = key;
            if (key === 'variety' && !this.filters.inventory_id && this.varieties.length) {
                const firstActive = this.varieties.find((v) => v.is_active) || this.varieties[0];
                this.filters.inventory_id = firstActive.inventory_id;
            }
            this.load();
        },
        async loadVarieties() {
            try {
                const r = await api.get('/api/admin/inventory', { params: { status: 'all', per_page: 100 } });
                this.varieties = r.data.data.slice().sort((a, b) => {
                    if (a.is_active !== b.is_active) return a.is_active ? -1 : 1;
                    return a.name.localeCompare(b.name);
                });
            } catch (e) {
                this.varieties = [];
            }
        },
        async load() {
            this.loading = true;
            try {
                const params = Object.fromEntries(this.query().entries());
                delete params.report;
                const r = await api.get(`/api/admin/reports/${this.report}`, { params });
                this.data = r.data.report;
            } catch (e) {
                this.$store.dispatch('toast', { text: errMsg(e, 'Could not load the report.'), variant: 'error' });
            } finally {
                this.loading = false;
            }
        },
    },
    async mounted() {
        const now = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const iso = (d) => d.toISOString().slice(0, 10);
        this.filters.from = iso(first);
        this.filters.to = iso(now);
        await this.loadVarieties();
        this.load();
    },
};
</script>
