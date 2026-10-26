<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Activity log</h1>
            <button class="btn btn-sm btn-outline-secondary" @click="loadLog()">Refresh</button>
        </div>

        <form class="card mb-3" @submit.prevent="loadLog()">
            <div class="card-body d-flex gap-2 flex-wrap">
                <input class="form-control" style="max-width: 220px" v-model="filters.action_type" placeholder="Action type" />
                <select class="form-select" style="max-width: 170px" v-model="filters.actor_type">
                    <option value="">All actors</option>
                    <option>Admin</option>
                    <option>Customer</option>
                    <option>System</option>
                </select>
                <input class="form-control" style="max-width: 160px" type="number" v-model.number="filters.entity_id" placeholder="Entity ID" />
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>
                <table v-else class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Entity</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td class="small">{{ fmtDateTime(row.created_at) }}</td>
                            <td class="fw-semibold">{{ row.action_type }}</td>
                            <td>
                                {{ row.actor_type }}
                                <span class="text-muted small">{{ row.actor_label || '' }}</span>
                            </td>
                            <td class="small">{{ row.entity_type || '—' }} {{ row.entity_id || '' }}</td>
                            <td class="small text-muted">{{ row.description || '—' }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="5" class="text-center text-muted py-4">No activity recorded.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :pagination="pagination" @page="loadLog" />
    </div>
</template>

<script>
import api from '../../api';
import Pagination from '../../components/Pagination.vue';
import { fmtDateTime } from '../../util';

export default {
    name: 'AdminActivityLog',
    components: { Pagination },
    data() {
        return {
            filters: { action_type: '', actor_type: '', entity_id: '' },
            rows: [],
            pagination: null,
            loading: true,
        };
    },
    methods: {
        fmtDateTime,
        async loadLog(page = 1) {
            this.loading = true;
            try {
                const params = { page };
                if (this.filters.action_type) params.action_type = this.filters.action_type;
                if (this.filters.actor_type) params.actor_type = this.filters.actor_type;
                if (this.filters.entity_id) params.entity_id = this.filters.entity_id;
                const r = await api.get('/api/admin/activity-log', { params });
                this.rows = r.data.data;
                this.pagination = r.data.pagination;
            } finally {
                this.loading = false;
            }
        },
    },
    mounted() {
        this.loadLog();
    },
};
</script>