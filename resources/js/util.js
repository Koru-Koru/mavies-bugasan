export function money(value, { blankForZero = false } = {}) {
    if (blankForZero && Number(value) === 0) return '—';
    return '₱' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function fmtDate(value) {
    return value ? String(value).slice(0, 10) : '—';
}

export function fmtDateTime(value) {
    return value ? String(value).replace('T', ' ').slice(0, 16) : '—';
}

export function statusVariant(status) {
    const map = {
        Pending: 'warning',
        Confirmed: 'primary',
        'Ready for Pickup': 'info',
        'Out for Delivery': 'info',
        Delivered: 'success',
        Cancelled: 'secondary',
    };
    return map[status] || 'secondary';
}

export function paymentVariant(status) {
    return { Paid: 'success', Partial: 'warning', Unpaid: 'danger' }[status] || 'secondary';
}

export function errMsg(error, fallback = 'Request failed. Please try again.') {
    const data = error && error.response && error.response.data;
    if (!data) return error && error.message ? error.message : fallback;
    if (data.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
        if (typeof first === 'string') return first;
    }
    return data.message || fallback;
}