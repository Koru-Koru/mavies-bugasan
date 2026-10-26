<template>
    <div>
        <h1 class="h3 mb-4">Dashboard</h1>

        <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>

        <template v-else>
            <!-- Two large KPI cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card kpi-card h-100">
                        <div class="card-body">
                            <div class="kpi-label">Revenue</div>
                            <div class="kpi-sub">this month · as delivered</div>
                            <div class="kpi-value">{{ money(data.stats.revenue_month) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card kpi-card h-100">
                        <div class="card-body">
                            <div class="kpi-label">Orders</div>
                            <div class="kpi-sub">open (not delivered, not cancelled)</div>
                            <div class="kpi-value">{{ data.stats.open_orders }}</div>
                            <div class="kpi-foot"><span class="text-muted small">{{ data.stats.orders_today }} placed today</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Three medium cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header">Sales last 14 days</div>
                        <div class="card-body">
                            <svg v-if="chart.length" viewBox="0 0 560 150" preserveAspectRatio="none" class="sales-chart" aria-label="Daily revenue, last 14 days">
                                <line x1="0" x2="560" y1="150" y2="150" stroke="#DFE9D3" stroke-width="1" />
                                <rect
                                    v-for="b in chart" :key="b.day"
                                    :x="b.x" :y="b.y" :width="b.w" :height="b.h"
                                    rx="2" fill="#5D8F2F"
                                >
                                    <title>{{ b.full }} · ₱{{ Number(b.revenue).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</title>
                                </rect>
                            </svg>
                            <div v-else class="text-muted small">No deliveries in the last 14 days.</div>
                            <div v-if="chart.length" class="d-flex justify-content-between text-muted small mt-1">
                                <span>{{ chart[0].full }}</span>
                                <span>today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card h-100">
                        <div class="card-header">Best seller</div>
                        <div class="card-body d-flex flex-column justify-content-center text-center">
                            <template v-if="data.best_seller">
                                <div class="h4 mb-1">{{ data.best_seller.name }}</div>
                                <div class="text-muted small">{{ data.best_seller.sacks }} sacks this month</div>
                                <div class="fs-5 fw-semibold mt-2">{{ money(data.best_seller.revenue) }}</div>
                            </template>
                            <div v-else class="text-muted small">No deliveries this month yet.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">Low stock alerts</div>
                        <div class="card-body">
                            <div v-if="!data.low_stock.length" class="text-muted small">
                                Everything is stocked. <span class="text-success fw-semibold">●</span>
                            </div>
                            <ul v-else class="list-group list-group-flush">
                                <li v-for="i in data.low_stock" :key="i.inventory_id"
                                    class="list-group-item small px-0 d-flex justify-content-between align-items-center">
                                    <span>{{ i.name }}</span>
                                    <span class="fw-semibold text-warning-emphasis">{{ i.stock }} / {{ i.low_stock_threshold }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full width recent orders -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recent orders</span>
                    <router-link class="btn btn-sm btn-outline-secondary" to="/admin/orders">All orders</router-link>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Customer</th>
                                <th>Fulfillment</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in data.recent_orders" :key="o.order_id"
                                class="cursor-pointer" @click="$router.push('/admin/orders/' + o.order_id)">
                                <td class="fw-semibold">{{ o.order_code }}</td>
                                <td>{{ o.customer_name }}</td>
                                <td>{{ o.fulfillment_method }}</td>
                                <td class="text-end">{{ money(o.amount_due) }}</td>
                                <td class="text-end">{{ money(o.balance, { blankForZero: true }) }}</td>
                                <td><span class="badge" :class="'text-bg-' + statusVariant(o.status)">{{ o.status }}</span></td>
                            </tr>
                            <tr v-if="!data.recent_orders.length">
                                <td colspan="6" class="text-center text-muted py-4">No recent orders.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import api from '../../api';
import { money, statusVariant } from '../../util';

export default {
    name: 'AdminDashboard',
    data() {
        return { data: { stats: {}, low_stock: [], recent_orders: [], sales_series: [], best_seller: null }, loading: true };
    },
    computed: {
        chart() {
            const series = this.data.sales_series || [];
            if (!series.length) return [];
            const max = Math.max(1, ...series.map((d) => Number(d.revenue) || 0));
            const slot = 560 / series.length;
            const barW = Math.max(4, Math.min(34, slot * 0.6));
            const top = 20;
            const base = 145;
            return series.map((d, i) => {
                const v = Number(d.revenue) || 0;
                const h = Math.max(2, Math.round((v / max) * (base - top)));
                return {
                    day: String(d.day).slice(5),
                    full: d.day,
                    revenue: v,
                    x: slot * i + (slot - barW) / 2,
                    y: base - h,
                    w: barW,
                    h,
                };
            });
        },
    },
    methods: { money, statusVariant },
    async mounted() {
        try {
            const r = await api.get('/api/admin/dashboard');
            this.data = r.data;
            this.data.sales_series = this.data.sales_series || [];
        } finally {
            this.loading = false;
        }
    },
};
</script>

<style scoped>
.kpi-card {
    background: linear-gradient(180deg, #F7FBF2, #FFFFFF);
}
.kpi-label {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6B7A5E;
}
.kpi-sub {
    font-size: .8rem;
    color: #8A9A7E;
}
.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2D4A1E;
    line-height: 1.2;
    margin: .25rem 0;
}
.sales-chart {
    width: 100%;
    height: 150px;
    display: block;
}
</style>