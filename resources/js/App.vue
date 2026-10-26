<template>
    <div :class="{ 'customer-app': !isAdminRoute }">
        <nav v-if="isAdminRoute && isAdmin" class="navbar navbar-expand-lg navbar-dark mb-4 admin-topbar">
            <div class="container">
                <router-link class="navbar-brand fw-semibold d-flex align-items-center gap-2" to="/admin">
                    <span class="brand-logo" aria-hidden="true">
                        <svg viewBox="0 0 120 120" fill="none">
                            <path d="M60 112 C59 78 57 46 56 20" stroke="#5D8F2F" stroke-width="7" stroke-linecap="round" />
                            <path d="M56 44 C42 38 32 42 30 52" stroke="#8FC348" stroke-width="7" stroke-linecap="round" />
                            <path d="M58 60 C72 54 82 58 84 68" stroke="#8FC348" stroke-width="7" stroke-linecap="round" />
                            <g fill="#A4D65E">
                                <ellipse cx="46" cy="28" rx="7" ry="4.5" transform="rotate(-24 46 28)" />
                                <ellipse cx="70" cy="24" rx="7" ry="4.5" transform="rotate(16 70 24)" />
                            </g>
                        </svg>
                    </span>
                    <span class="brand-title">Mavies Bugasan</span>
                </router-link>
                <button class="navbar-toggler" type="button" @click="navOpen = !navOpen" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" :class="{ show: navOpen }">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <li class="nav-item">
                            <router-link class="nav-link d-lg-flex align-items-center gap-1" to="/admin">
                                <HomeIcon :size="16" />
                                <span class="d-lg-none">Dashboard</span>
                            </router-link>
                        </li>
                        <li class="nav-item">
                            <span class="navbar-text text-white me-2">{{ authUser.admin_name }}</span>
                        </li>
                        <li class="nav-item">
                            <button class="btn btn-outline-light btn-sm" @click="signOut">Sign out</button>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <router-view />

        <toast />
    </div>
</template>

<script>
import Toast from './components/Toast.vue';
import { Home as HomeIcon } from 'lucide-vue';

export default {
    name: 'App',
    components: { Toast, HomeIcon },
    data() {
        return { navOpen: false };
    },
    computed: {
        isAdminRoute() {
            return String(this.$route.path).startsWith('/admin');
        },
        isAdmin() {
            return this.$store.getters.isAdmin;
        },
        authUser() {
            return this.$store.getters.authUser;
        },
    },
    methods: {
        async signOut() {
            await this.$store.dispatch('logout', this.$store.state.auth.guard);
            this.$router.push('/');
        },
    },
    watch: {
        isAdminRoute(now, before) {
            if (now !== before) this.navOpen = false;
        },
    },
};
</script>

<style>
.navbar {
    background: linear-gradient(180deg, #8FC348, #5D8F2F);
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.12);
}

.admin-topbar {
    position: sticky;
    top: 0;
    z-index: 1030;
}

.navbar-brand .brand-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 4px;
    background: #FFFFFF;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.18);
    flex-shrink: 0;
}

.navbar-brand .brand-logo svg {
    width: 100%;
    height: 100%;
}

.navbar-brand .brand-title {
    line-height: 1.1;
}

.navbar .nav-link {
    color: rgba(255, 255, 255, 0.85);
}

.navbar .nav-link.router-link-exact-active,
.navbar .nav-link:hover {
    color: #ffffff;
}

#app {
    min-height: 100vh;
}
</style>