<template>
    <div class="customer-screen">
        <div class="splash">
            <svg class="splash-logo" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                <path d="M60 112 C59 78 57 46 56 20" stroke="#5D8F2F" stroke-width="4" stroke-linecap="round" />
                <path d="M56 44 C42 38 32 42 30 52" stroke="#8FC348" stroke-width="4" stroke-linecap="round" />
                <path d="M58 60 C72 54 82 58 84 68" stroke="#8FC348" stroke-width="4" stroke-linecap="round" />
                <path d="M56 76 C44 72 36 76 34 84" stroke="#8FC348" stroke-width="4" stroke-linecap="round" />
                <g fill="#A4D65E">
                    <ellipse cx="46" cy="28" rx="7" ry="4.5" transform="rotate(-24 46 28)" />
                    <ellipse cx="70" cy="24" rx="7" ry="4.5" transform="rotate(16 70 24)" />
                    <ellipse cx="40" cy="12" rx="6" ry="4" transform="rotate(-30 40 12)" />
                    <ellipse cx="72" cy="10" rx="6" ry="4" transform="rotate(12 72 10)" />
                </g>
            </svg>
            <h1>Mavies Bugasan</h1>
            <div class="tag">at iba pa.</div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CustomerSplash',
    mounted() {
        window.setTimeout(() => {
            const guard = this.$store.state.auth.guard;
            this.$router.replace(guard === 'customer' ? '/orders' : guard === 'admin' ? '/admin' : '/login');
        }, 800);
    },
};
</script>