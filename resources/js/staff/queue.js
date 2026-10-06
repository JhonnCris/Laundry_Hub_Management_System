/**
 * Active Laundry: status forward + undo
 */
import { api, esc, paginate } from './core.js';

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
    paginate(box, { reset: true });
}

/** One short sentence describing what happened to the customer notification. */
function notifyText(n) {
    if (!n) return '';
    const word = { sent: 'sent', logged: 'not sent (no email/SMS provider set up yet)', skipped: 'skipped (no contact on file)', failed: 'failed' };
    const push = n.push && n.push !== 'skipped' ? `; app push: ${word[n.push] || n.push}` : '';
    return ` Customer notice — email: ${word[n.email] || n.email}; SMS: ${word[n.sms] || n.sms}${push}.`;
}

export function handleHistorySearch(t) {
    if (!t.matches('[data-history-search]')) return false;
    clearTimeout(t._debounce);
    t._debounce = setTimeout(() => {
        const q = (t.value || '').toLowerCase().trim();
        document.querySelectorAll('[data-history-item]').forEach((row) => {
            row.hidden = !!q && !row.dataset.hay.includes(q);
        });
        paginate(document.querySelector('[data-history-rows]'), { reset: true });
    }, 150);
    return true;
}

export function handleQueueClick(t, e, ctx) {
    const { openConfirm, openNotice, openError, showModal, hideModal } = ctx;

    if (t.closest('[data-open-history]')) {
        e.preventDefault();
        const search = document.querySelector('[data-history-search]');
        if (search) search.value = '';
        showModal('[data-history-modal]');
        api('/ajax/staff/history')
            .then((r) => renderHistory(r.orders || []))
            .catch((ex) => openError('History', ex));
        return true;
    }
    if (t.closest('[data-close-history]')) {
        e.preventDefault();
        hideModal('[data-history-modal]');
        return true;
    }
    const cancelOpen = t.closest('[data-cancel-order]');
    if (cancelOpen) {
        e.preventDefault();
        const reasonEl = document.querySelector('[data-cancel-order-reason]');
        const errEl = document.querySelector('[data-cancel-order-error]');
        document.querySelector('[data-cancel-order-id]').value = cancelOpen.dataset.cancelOrder;
        if (reasonEl) reasonEl.value = '';
        if (errEl) errEl.hidden = true;
        showModal('[data-cancel-order-modal]');
        return true;
    }
    if (t.closest('[data-close-cancel-order]')) {
        e.preventDefault();
        hideModal('[data-cancel-order-modal]');
        return true;
    }
    if (t.closest('[data-confirm-cancel-order]')) {
        e.preventDefault();
        const id = document.querySelector('[data-cancel-order-id]')?.value;
        const reason = (document.querySelector('[data-cancel-order-reason]')?.value || '').trim();
        const errEl = document.querySelector('[data-cancel-order-error]');
        const showErr = (msg) => {
            if (errEl) {
                errEl.hidden = false;
                errEl.textContent = msg;
            }
        };
        if (reason.length < 3) {
            showErr('Please give a short reason (at least 3 letters).');
            return true;
        }
        api(`/ajax/staff/transactions/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) })
            .then((r) => {
                hideModal('[data-cancel-order-modal]');
                openNotice('Order cancelled', r.message || 'The order is now in Cancelled orders.', 'success');
                ctx.loadStaffBootstrap?.();
            })
            .catch((ex) => showErr(ex.message));
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
        api(`/ajax/staff/transactions/${id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status: next }),
        })
            .then((r) => {
                ctx.loadStaffBootstrap?.();
                openNotice(
                    isUndo ? 'Status undone' : 'Status updated',
                    (isUndo ? `Order moved back to ${label}.` : `Order marked as ${label}.`) + notifyText(r?.notify),
                    'success'
                );
            })
            .catch((err) => {
                ctx.loadStaffBootstrap?.();
                openError('Status', err);
            });

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
