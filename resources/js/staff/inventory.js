/**
 * Inventory: archive items + add baskets
 */
import { api, qtyUnit } from './core.js';

export function handleInventoryClick(t, e, ctx) {
    const { showModal, hideModal, openConfirm, openNotice, openError } = ctx;

    const stockToggle = t.closest('[data-stock-toggle]');
    if (stockToggle) {
        e.preventDefault();
        const submenu = ctx.app.querySelector('[data-stock-subnav]');
        const isExpanded = stockToggle.getAttribute('aria-expanded') === 'true';
        stockToggle.setAttribute('aria-expanded', String(!isExpanded));
        if (submenu) submenu.hidden = isExpanded;
        return true;
    }

    const notifyBtn = t.closest('[data-notify-low]');
    if (notifyBtn) {
        e.preventDefault();
        notifyBtn.disabled = true;
        api(`/ajax/staff/inventory/${notifyBtn.dataset.notifyLow}/notify-low`, { method: 'POST', body: '{}' })
            .then((r) => {
                notifyBtn.textContent = 'Admin notified ✓';
                openNotice('Admin notified', r.message || 'The admin will see this in their alerts.', 'success');
            })
            .catch((ex) => {
                notifyBtn.disabled = false;
                openError('Notify admin', ex);
            });
        return true;
    }

    if (t.closest('[data-open-add-basket]')) {
        e.preventDefault();
        const err = document.querySelector('[data-add-basket-error]');
        if (err) err.hidden = true;
        const input = document.querySelector('[data-new-basket-code]');
        if (input) input.value = '';
        showModal('[data-add-basket-modal]');
        return true;
    }
    if (t.closest('[data-close-add-basket]')) {
        e.preventDefault();
        hideModal('[data-add-basket-modal]');
        return true;
    }
    if (t.closest('[data-confirm-add-basket]')) {
        e.preventDefault();
        const code = (document.querySelector('[data-new-basket-code]')?.value || '').trim();
        const err = document.querySelector('[data-add-basket-error]');
        if (!code) {
            if (err) {
                err.hidden = false;
                err.textContent = 'Enter a basket code (e.g. 021 or #021).';
            }
            return true;
        }
        api('/ajax/staff/baskets', {
            method: 'POST',
            body: JSON.stringify({ code }),
        })
            .then((r) => {
                hideModal('[data-add-basket-modal]');
                openNotice('Basket added', (r.basket?.code || code) + ' is now available.', 'success');
                ctx.loadStaffBootstrap?.();
            })
            .catch((ex) => {
                if (err) {
                    err.hidden = false;
                    err.textContent = ex.message;
                }
            });
        return true;
    }

    if (t.closest('[data-open-archive]')) {
        e.preventDefault();
        showModal('[data-archive-modal]');
        return true;
    }
    if (t.closest('[data-close-archive]')) {
        e.preventDefault();
        hideModal('[data-archive-modal]');
        return true;
    }
    if (t.closest('[data-confirm-archive]')) {
        e.preventDefault();
        const itemId = document.querySelector('[data-archive-item]')?.value;
        const reason = document.querySelector('[data-archive-reason]')?.value;
        const qty = Number(document.querySelector('[data-archive-qty]')?.value || 0);
        const err = document.querySelector('[data-archive-error]');
        const stock = ((ctx.app._bootstrap || ctx.app._adminBootstrap || {}).inventory || []).find((i) => String(i.id) === String(itemId));
        const problem = !stock
            ? 'Pick an item that still has stock.'
            : !Number.isInteger(qty) || qty < 1
              ? 'Enter how many to remove (1 or more).'
              : qty > Number(stock.quantity_on_hand)
                ? `Only ${qtyUnit(stock.quantity_on_hand, stock.unit)} of ${stock.name} in stock.`
                : '';
        if (problem) {
            if (err) {
                err.hidden = false;
                err.textContent = problem;
            }
            return true;
        }
        if (err) err.hidden = true;
        openConfirm(
            'Archive stock?',
            `Remove ${qtyUnit(qty, stock.unit)} of ${stock.name} as ${reason}. This reduces inventory and is kept in the Archived records.`,
            () => {
                api(`/ajax/staff/inventory/${itemId}/archive`, {
                    method: 'POST',
                    body: JSON.stringify({ reason, quantity: qty }),
                })
                    .then((r) => {
                        hideModal('[data-archive-modal]');
                        openNotice('Archive', r.message || 'Archived', 'success');
                        ctx.loadStaffBootstrap?.();
                    })
                    .catch((ex) => {
                        if (err) {
                            err.hidden = false;
                            err.textContent = ex.message;
                        }
                    });
            }
        );
        return true;
    }

    return false;
}
