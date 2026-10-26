import Vue from 'vue';
import VueRouter from 'vue-router';
import store from './store';

import CustomerSplash from './pages/CustomerSplash.vue';
import SignIn from './pages/SignIn.vue';
import SignUp from './pages/SignUp.vue';
import RegisterVerify from './pages/RegisterVerify.vue';
import RegisterSuccess from './pages/RegisterSuccess.vue';
import ForgotPassword from './pages/ForgotPassword.vue';
import ResetPassword from './pages/ResetPassword.vue';
import CustomerOrders from './pages/Orders.vue';
import CustomerProfile from './pages/CustomerProfile.vue';
import AdminLogin from './pages/AdminLogin.vue';
import AdminForgotPassword from './pages/admin/ForgotPassword.vue';
import AdminResetPassword from './pages/admin/ResetPassword.vue';

import AdminLayout from './pages/admin/AdminLayout.vue';
import AdminDashboard from './pages/admin/Dashboard.vue';
import AdminOrders from './pages/admin/OrdersIndex.vue';
import AdminOrderShow from './pages/admin/OrderShow.vue';
import AdminInventory from './pages/admin/Inventory.vue';
import AdminCustomers from './pages/admin/Customers.vue';
import AdminCustomerShow from './pages/admin/CustomerShow.vue';
import AdminPayments from './pages/admin/Payments.vue';
import AdminDamages from './pages/admin/Damages.vue';
import AdminReports from './pages/admin/Reports.vue';
import AdminSettings from './pages/admin/Settings.vue';
import AdminActivityLog from './pages/admin/ActivityLog.vue';

Vue.use(VueRouter);

const routes = [
    // Guest app: guests land on the splash which auto-advances after 0.8s.
    { path: '/', name: 'splash', component: CustomerSplash },
    { path: '/login', name: 'login', component: SignIn, meta: { guestFor: 'customer' } },
    { path: '/register', name: 'register', component: SignUp, meta: { guestFor: 'customer' } },
    { path: '/register/verify', name: 'register-verify', component: RegisterVerify, meta: { guestFor: 'customer' } },
    { path: '/register/success', name: 'register-success', component: RegisterSuccess, meta: { guestFor: 'customer' } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPassword },
    { path: '/reset-password', name: 'reset-password', component: ResetPassword },
    { path: '/orders', name: 'orders', component: CustomerOrders, meta: { requires: 'customer' } },
    { path: '/profile', name: 'profile', component: CustomerProfile, meta: { requires: 'customer' } },
    { path: '/admin/login', name: 'admin-login', component: AdminLogin, meta: { guestFor: 'admin' } },
    { path: '/admin/forgot-password', name: 'admin-forgot-password', component: AdminForgotPassword },
    { path: '/admin/reset-password', name: 'admin-reset-password', component: AdminResetPassword },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requires: 'admin' },
        children: [
            { path: '', name: 'admin-dashboard', component: AdminDashboard },
            { path: 'orders', name: 'admin-orders', component: AdminOrders },
            { path: 'orders/:orderId', name: 'admin-order', component: AdminOrderShow, props: true },
            { path: 'inventory', name: 'admin-inventory', component: AdminInventory },
            { path: 'customers', name: 'admin-customers', component: AdminCustomers },
            { path: 'customers/:customerId', name: 'admin-customer', component: AdminCustomerShow, props: true },
            { path: 'payments', name: 'admin-payments', component: AdminPayments },
            { path: 'damages', name: 'admin-damages', component: AdminDamages },
            { path: 'reports', name: 'admin-reports', component: AdminReports },
            { path: 'settings', name: 'admin-settings', component: AdminSettings },
            { path: 'activity-log', name: 'admin-activity-log', component: AdminActivityLog },
        ],
    },
    { path: '*', redirect: '/' },
];

const router = new VueRouter({
    mode: 'history',
    routes,
    scrollBehavior: () => ({ x: 0, y: 0 }),
});

router.beforeEach(async (to, from, next) => {
    // Parent routes carry the guard (e.g. /admin children); to.meta only holds
    // the matched leaf, so walk the whole matched chain.
    const required = to.matched.reduce((found, record) => found || record.meta.requires || null, null);

    if (!store.state.auth.ready) {
        await store.dispatch('boot', required);
    }

    if (required) {
        if (store.state.auth.guard !== required) {
            const redirect = required === 'admin' ? '/admin/login' : '/login';
            return next({ path: redirect, query: { redirect: to.fullPath } });
        }
        return next();
    }

    if (to.meta.guestFor && store.state.auth.guard === to.meta.guestFor) {
        return next(to.meta.guestFor === 'admin' ? '/admin' : '/orders');
    }

    return next();
});

export default router;