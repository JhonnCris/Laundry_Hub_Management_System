/**
 * Shared API helpers (money, CSRF, fetch wrapper)
 */
export function money(n) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(n) || 0);
}

export function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/** Plain-language replies for failures the server did not explain itself. */
const STATUS_HELP = {
    401: 'Your session has ended. Please log in again.',
    403: 'You are not allowed to do this.',
    404: 'That record could not be found. Refresh the page and try again.',
    419: 'This page has expired. Refresh the page and try again.',
    429: 'Too many requests. Please wait a moment and try again.',
};

/** Error with a message that is safe to show to staff, plus the HTTP status (0 = no connection). */
export class ApiError extends Error {
    constructor(message, status = 0) {
        super(message);
        this.status = status;
    }
}

/** Text to show a person for any thrown error. */
export function errorText(err) {
    return err?.message || 'Something went wrong. Please try again.';
}

export async function api(url, options = {}) {
    let res;
    try {
        res = await fetch(url, {
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
    } catch (e) {
        throw new ApiError("Can't reach the server. Check your internet connection and try again.");
    }
    let body = null;
    try {
        body = await res.json();
    } catch (e) {
        body = null;
    }
    if (!res.ok) {
        const firstField = body?.errors ? Object.values(body.errors).flat()[0] : null;
        const explained = firstField || body?.message || body?.error;
        const msg = res.status >= 500
            ? 'Something went wrong on the server. Please try again; if it keeps happening, tell the admin.'
            : STATUS_HELP[res.status === 403 && explained ? 0 : res.status] || explained || `Request failed (${res.status}). Please try again.`;
        throw new ApiError(msg, res.status);
    }
    if (body === null) {
        throw new ApiError(`The server sent an unreadable reply (status ${res.status}). Please refresh; if it keeps happening, check the server logs.`, res.status);
    }
    return body;
}

/** Floating message in the corner: type is "error" (red), "success" (green) or "info" (blue). */
export function toast(message, type = 'error') {
    let stack = document.querySelector('[data-toast-stack]');
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toast-stack';
        stack.dataset.toastStack = '';
        stack.setAttribute('aria-live', 'assertive');
        document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss');
    close.textContent = '×';
    close.addEventListener('click', () => el.remove());
    el.append(text, close);
    stack.appendChild(el);
    setTimeout(() => el.remove(), type === 'error' ? 9000 : 5000);
}

const PAGE_SIZE = 8;

/**
 * Show one page of the rows inside `box` and add Prev/Next controls after it.
 * Rows hidden by a search filter (the hidden attribute) are skipped. The pager only
 * appears when there is more than one page. Pass reset to jump back to page 1.
 */
export function paginate(box, { reset = false, size = PAGE_SIZE } = {}) {
    if (!box) return;
    const rows = [...box.children].filter((row) => !row.hidden && !row.hasAttribute('data-page-skip'));
    const pages = Math.max(1, Math.ceil(rows.length / size));
    if (reset) box._page = 1;
    box._page = Math.min(Math.max(box._page || 1, 1), pages);
    rows.forEach((row, i) => {
        row.toggleAttribute('data-page-hidden', Math.floor(i / size) + 1 !== box._page);
    });
    if (!box._pager) {
        const bar = document.createElement('div');
        bar.className = 'pager';
        bar.innerHTML = '<button type="button" class="pager-btn" data-pager-prev>‹ Prev</button><span class="pager-info"></span><button type="button" class="pager-btn" data-pager-next>Next ›</button>';
        bar.querySelector('[data-pager-prev]').addEventListener('click', () => {
            box._page -= 1;
            paginate(box, { size });
        });
        bar.querySelector('[data-pager-next]').addEventListener('click', () => {
            box._page += 1;
            paginate(box, { size });
        });
        box.after(bar);
        box._pager = bar;
    }
    const bar = box._pager;
    bar.hidden = pages === 1;
    bar.querySelector('.pager-info').textContent = `Page ${box._page} of ${pages} · ${rows.length} rows`;
    bar.querySelector('[data-pager-prev]').disabled = box._page <= 1;
    bar.querySelector('[data-pager-next]').disabled = box._page >= pages;
}

/** "5 pieces", "1 sachet": a quantity with its unit spelled out (old "pc" values read as piece). */
export function qtyUnit(qty, unit) {
    let u = String(unit || '').trim().toLowerCase();
    if (u === 'pc' || u === 'pcs') u = 'piece';
    if (!u) return String(qty);
    if (Number(qty) === 1 || u.endsWith('s')) return `${qty} ${u}`;
    return `${qty} ${u}${/(x|ch|sh)$/.test(u) ? 'es' : 's'}`;
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
