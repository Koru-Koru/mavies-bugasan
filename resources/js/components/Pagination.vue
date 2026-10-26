<template>
    <nav v-if="pagination && pagination.pages > 1" class="d-flex justify-content-between align-items-center mt-4">
        <span class="text-muted small">
            Page {{ pagination.page }} of {{ pagination.pages }} ({{ pagination.total }} total)
        </span>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" :disabled="pagination.page <= 1" @click="$emit('page', pagination.page - 1)">
                Prev
            </button>
            <button class="btn btn-outline-secondary btn-sm"
                :disabled="pagination.page >= pagination.pages" @click="$emit('page', pagination.page + 1)">
                Next
            </button>
        </div>
    </nav>
</template>

<script>
export default {
    name: 'Pagination',
    props: {
        pagination: { type: Object, default: null },
    },
};
</script>