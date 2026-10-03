/**
 * Active Laundry: status forward + undo
 */
import { api, esc } from './core.js';

export function statusLabel(st) {
    const map = {
        pending: 'Pending',
        processing: 'Processing',
        ready_for_pickup: 'Ready for pickup',
        claimed: 'Claimed',
        cancelled: 'Cancelled',
    };
    return map[st] || st;
}

export function statusClass(st) {
    if (st === 'processing') return 'processing';
    if (st === 'ready_for_pickup') return 'ready';
    if (st === 'pending') return 'pending';
    return 'ready';
}

function renderHistory(orders) {
    const box = document.querySelector('[data-history-rows]');
    if (!box) return;
    box.innerHTML = orders.length
        ? orders
              .map((tx) => {
                  const when = String((tx.status_logs && tx.status_logs[0]?.changed_at) || tx.updated_at || '').replace('T', ' ').slice(0, 16);
                  const code = tx.basket_tag?.code || '#' + tx.id;
                  const customer = tx.customer?.name || '—';
                  const service = tx.service?.name || (tx.transaction_type === 'self_service' ? 'Self Service' : 'Drop Off');
                  const total = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(tx.total_amount) || 0);
                  const hay = `${customer} ${code} ${service}`.toLowerCase();
                  return `<div class="history-row" data-history-item data-hay="${esc(hay)}"><span>${esc(when)}</span><strong>${esc(code)}</strong><span>${esc(customer)}</span><span>${esc(service)}</span><span class="num">${total}</span></div>`;
              })
              .join('')
        : '<div class="history-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No completed orders yet.</span></div>';
}

export function handleHistorySearch(t) {
    if (!t.matches('[data-history-search]')) return false;
    const q = (t.value || '').toLowerCase().trim();
    document.querySelectorAll('[data-history-item]').forEach((row) => {
        row.hidden = !!q && !row.dataset.hay.includes(q);
    });
    return true;
}

export function handleQueueClick(t, e, ctx) {
    const { openConfirm, openNotice, showModal, hideModal } = ctx;

    if (t.closest('[data-open-history]')) {
        e.preventDefault();
        const search = document.querySelector('[data-history-search]');
        if (search) search.value = '';
        showModal('[data-history-modal]');
        api('/api/staff/history')
            .then((r) => renderHistory(r.orders || []))
            .catch((ex) => openNotice('History', ex.message));
        return true;
    }
    if (t.closest('[data-close-history]')) {
        e.preventDefault();
        hideModal('[data-history-modal]');
        return true;
    }
    const statusBtn = t.closest('[data-set-status]');
    if (!statusBtn) return false;

    e.preventDefault();
    const id = statusBtn.dataset.setStatus;
    const next = statusBtn.dataset.nextStatus;
    const label = statusLabel(next);
    const isUndo = statusBtn.dataset.undo === '1';
    const run = () =>
        api(`/api/staff/transactions/${id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status: next }),
        })
            .then(() => {
                ctx.loadStaffBootstrap?.();
                openNotice(
                    isUndo ? 'Status undone' : 'Status updated',
                    isUndo ? `Order moved back to ${label}.` : `Order marked as ${label}.`
                );
            })
            .catch((err) => openNotice('Status', err.message));

    if (next === 'claimed') {
        openConfirm(
            'Mark as claimed?',
            'Only continue if the customer already picked up this order. Claimed orders cannot be undone.',
            run
        );
    } else if (isUndo) {
        openConfirm(
            'Undo status?',
            `Move this order back to “${label}”. Use this if the previous status was clicked by mistake.`,
            run
        );
    } else {
        openConfirm(
            `Mark as ${label}?`,
            `Update this order status to “${label}”. You can undo later unless it is marked Claimed.`,
            run
        );
    }
    return true;
}
