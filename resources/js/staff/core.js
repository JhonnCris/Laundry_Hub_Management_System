/**
 * Shared API helpers (money, CSRF, fetch wrapper)
 */
export function money(n) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(n) || 0);
}

export function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export async function api(url, options = {}) {
    const res = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
        ...options,
    });
    let body = null;
    try {
        body = await res.json();
    } catch (e) {
        body = null;
    }
    if (!res.ok) {
        const msg = body?.message || body?.error || `Request failed (${res.status})`;
        throw new Error(msg);
    }
    if (body === null) {
        throw new Error(`The server sent an unreadable reply (status ${res.status}). Please refresh; if it keeps happening, check the server logs.`);
    }
    return body;
}

export function fmtTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    } catch (e) {
        return '—';
    }
}

export function fmtDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        const today = new Date();
        if (d.toDateString() === today.toDateString()) return 'Today';
        return d.toLocaleDateString();
    } catch (e) {
        return '—';
    }
}

/** Receipt/shop settings printed from config/shop.php (set on the app element by Blade). */
export function shopInfo(app) {
    return {
        name: app.dataset.shopName || 'SSK Laba Dami',
        tagline: app.dataset.shopTagline || '',
        address: app.dataset.shopAddress || '',
        claimDays: Number(app.dataset.claimDays) || 7,
    };
}

export function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
