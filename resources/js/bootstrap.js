import axios from 'axios';

window.axios = axios;

// The browser login that the previous build broke: this is the whole fix.
// The CSRF token is read from a meta tag rendered by the Blade shell and
// sent on every request, so session + CSRF work through the web middleware
// group rather than a hand-rolled api() wrapper (spec §1.1, RC1).
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
