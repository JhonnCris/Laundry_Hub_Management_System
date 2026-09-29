/**
 * Active Laundry: status forward + undo
 */
import { api } from './core.js';

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

export function handleQueueClick(t, e, ctx) {
    const { openConfirm, openNotice } = ctx;
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
