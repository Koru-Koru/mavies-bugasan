<template>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000">
        <transition name="toast">
            <div v-if="toast.show" class="toast show align-items-center border-0" :class="'text-bg-' + toast.variant"
                role="alert" @click="$store.dispatch('clearToast')">
                <div class="d-flex">
                    <div class="toast-body">{{ toast.text }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        @click="$store.dispatch('clearToast')"></button>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
export default {
    name: 'Toast',
    computed: {
        toast() {
            return this.$store.state.toast;
        },
    },
};
</script>