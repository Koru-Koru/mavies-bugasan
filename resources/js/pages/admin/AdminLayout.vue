<template>
    <main class="container-fluid px-4">
        <div class="row g-4">
            <aside class="col-lg-2 d-none d-lg-block">
                <nav class="nav flex-column pt-2">
                    <router-link class="nav-link" exact to="/admin">
                        <HomeIcon :size="16" class="nav-icon" />
                        <span>Dashboard</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/orders">
                        <ShoppingCartIcon :size="16" class="nav-icon" />
                        <span>Orders</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/inventory">
                        <BoxesIcon :size="16" class="nav-icon" />
                        <span>Inventory</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/customers">
                        <UsersIcon :size="16" class="nav-icon" />
                        <span>Customers</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/payments">
                        <WalletIcon :size="16" class="nav-icon" />
                        <span>Payments</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/damages">
                        <FileWarningIcon :size="16" class="nav-icon" />
                        <span>Damages</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/reports">
                        <BarChart3Icon :size="16" class="nav-icon" />
                        <span>Reports</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/settings">
                        <SettingsIcon :size="16" class="nav-icon" />
                        <span>Settings</span>
                    </router-link>
                    <router-link class="nav-link" to="/admin/activity-log">
                        <ActivityIcon :size="16" class="nav-icon" />
                        <span>Activity log</span>
                    </router-link>
                </nav>
            </aside>

            <section class="col-lg-10">
                <div class="card mb-3 d-lg-none">
                    <div class="card-body py-2 d-flex gap-2 flex-wrap">
                        <router-link class="btn btn-sm btn-outline-secondary" exact to="/admin">Dashboard</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/orders">Orders</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/inventory">Inventory</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/customers">Customers</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/payments">Payments</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/damages">Damages</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/reports">Reports</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/settings">Settings</router-link>
                        <router-link class="btn btn-sm btn-outline-secondary" to="/admin/activity-log">Log</router-link>
                    </div>
                </div>

                <router-view />
            </section>
        </div>
    </main>
</template>

<script>
import { Home as HomeIcon, ShoppingCart as ShoppingCartIcon, Boxes as BoxesIcon, Users as UsersIcon, Wallet as WalletIcon, FileWarning as FileWarningIcon, BarChart3 as BarChart3Icon, Settings as SettingsIcon, Activity as ActivityIcon } from 'lucide-vue';

export default {
    name: 'AdminLayout',
    components: {
        HomeIcon,
        ShoppingCartIcon,
        BoxesIcon,
        UsersIcon,
        WalletIcon,
        FileWarningIcon,
        BarChart3Icon,
        SettingsIcon,
        ActivityIcon,
    },
};
</script>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #334155;
    padding: 0.5rem 0.75rem;
}

.nav-link .nav-icon {
    flex-shrink: 0;
    opacity: 0.85;
}

.nav-link.router-link-exact-active,
.nav-link.router-link-active {
    font-weight: 600;
    color: #2D4A1E;
    background: #EAF3DC;
    border-radius: 6px;
}
</style>