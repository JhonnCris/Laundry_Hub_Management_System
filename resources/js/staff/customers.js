/**
 * Manage Customer module
 */
import { api } from './core.js';

export function getSelectedCustomer(app) {
    if (app._selectedCustomer && app._selectedCustomer.id) return app._selectedCustomer;
    try {
        const raw = sessionStorage.getItem('ssk_selected_customer');
        if (raw) {
            const c = JSON.parse(raw);
            if (c && c.id) {
                app._selectedCustomer = c;
                return c;
            }
        }
    } catch (e) {}
    return null;
}

export function customerFullName(app) {
    const c = getSelectedCustomer(app);
    return c?.name || '';
}

export function renderCustomerContext(app) {
    const c = getSelectedCustomer(app);
    const empty = app.querySelector('[data-customer-empty]');
    const selected = app.querySelector('[data-customer-selected]');
    const nameEl = app.querySelector('[data-tx-customer-name]');
    const phoneEl = app.querySelector('[data-tx-customer-phone]');
    if (!empty || !selected) return;
    if (c && c.id) {
        empty.hidden = true;
        selected.hidden = false;
        if (nameEl) nameEl.textContent = c.name || '—';
        if (phoneEl) phoneEl.textContent = c.contact_number || '';
    } else {
        empty.hidden = false;
        selected.hidden = true;
        if (nameEl) nameEl.textContent = '—';
        if (phoneEl) phoneEl.textContent = '';
    }
}

export function clearSelectedCustomer(app) {
    app._selectedCustomer = null;
    try {
        sessionStorage.removeItem('ssk_selected_customer');
    } catch (e) {}
    renderCustomerContext(app);
}

export function goToTransactionsForCustomer(app, customer, updateFn) {
    app._selectedCustomer = customer;
    try {
        sessionStorage.setItem('ssk_selected_customer', JSON.stringify(customer));
    } catch (e) {}
    const txBtn = app.querySelector('[data-screen="transactions"]');
    if (txBtn) txBtn.click();
    renderCustomerContext(app);
    const notice = app.querySelector('[data-save-notice]');
    if (notice) {
        notice.textContent = '';
        notice.classList.remove('is-error');
    }
    if (typeof updateFn === 'function') updateFn();
}

/**
 * @returns {boolean} true if event handled
 */
export function handleCustomerClick(t, e, ctx) {
    const { app, showModal, hideModal, openNotice, update } = ctx;

    if (t.closest('[data-change-customer]')) {
        e.preventDefault();
        clearSelectedCustomer(app);
        app.querySelector('[data-screen="customers"]')?.click();
        return true;
    }
    if (t.closest('[data-new-tx-same-customer]')) {
        e.preventDefault();
        hideModal('[data-receipt-modal]');
        ctx.resetTransactionForm?.();
        renderCustomerContext(app);
        app.querySelector('[data-screen="transactions"]')?.click();
        return true;
    }
    if (t.closest('[data-back-to-customers]')) {
        e.preventDefault();
        hideModal('[data-receipt-modal]');
        ctx.resetTransactionForm?.();
        clearSelectedCustomer(app);
        app.querySelector('[data-screen="customers"]')?.click();
        return true;
    }

    if (t.closest('[data-open-add-customer]')) {
        e.preventDefault();
        const err = document.querySelector('[data-add-customer-error]');
        if (err) err.hidden = true;
        document
            .querySelectorAll('[data-new-customer-first],[data-new-customer-middle],[data-new-customer-last],[data-new-customer-phone],[data-new-customer-email],[data-new-customer-address]')
            .forEach((el) => {
                el.value = '';
            });
        showModal('[data-add-customer-modal]');
        return true;
    }
    if (t.closest('[data-close-add-customer]')) {
        e.preventDefault();
        hideModal('[data-add-customer-modal]');
        return true;
    }
    if (t.closest('[data-confirm-add-customer]')) {
        e.preventDefault();
        const first = (document.querySelector('[data-new-customer-first]')?.value || '').trim();
        const middle = (document.querySelector('[data-new-customer-middle]')?.value || '').trim();
        const last = (document.querySelector('[data-new-customer-last]')?.value || '').trim();
        const phone = (document.querySelector('[data-new-customer-phone]')?.value || '').trim();
        const email = (document.querySelector('[data-new-customer-email]')?.value || '').trim();
        const address = (document.querySelector('[data-new-customer-address]')?.value || '').trim();
        const err = document.querySelector('[data-add-customer-error]');
        if (!first || !last || !phone) {
            if (err) {
                err.hidden = false;
                err.textContent = 'First name, last name and phone/SMS are required.';
            }
            return true;
        }
        api('/ajax/staff/customers', {
            method: 'POST',
            body: JSON.stringify({
                first_name: first,
                middle_name: middle || null,
                last_name: last,
                contact_number: phone,
                email: email || null,
                address: address || null,
            }),
        })
            .then((r) => {
                hideModal('[data-add-customer-modal]');
                const c = r.customer;
                goToTransactionsForCustomer(app, {
                    id: c.id,
                    name: c.name,
                    contact_number: c.contact_number,
                    email: c.email,
                }, update);
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

    const editBtn = t.closest('[data-edit-customer]');
    if (editBtn) {
        e.preventDefault();
        const err = document.querySelector('[data-edit-customer-error]');
        if (err) err.hidden = true;
        document.querySelector('[data-edit-customer-id]').value = editBtn.dataset.editCustomer;
        document.querySelector('[data-edit-customer-name]').value = editBtn.dataset.customerName || '';
        document.querySelector('[data-edit-customer-phone]').value = editBtn.dataset.customerPhone || '';
        document.querySelector('[data-edit-customer-email]').value = editBtn.dataset.customerEmail || '';
        showModal('[data-edit-customer-modal]');
        return true;
    }
    if (t.closest('[data-close-edit-customer]')) {
        e.preventDefault();
        hideModal('[data-edit-customer-modal]');
        return true;
    }
    if (t.closest('[data-confirm-edit-customer]')) {
        e.preventDefault();
        const id = document.querySelector('[data-edit-customer-id]')?.value;
        const phone = (document.querySelector('[data-edit-customer-phone]')?.value || '').trim();
        const email = (document.querySelector('[data-edit-customer-email]')?.value || '').trim();
        const err = document.querySelector('[data-edit-customer-error]');
        if (!phone) {
            if (err) {
                err.hidden = false;
                err.textContent = 'Phone / SMS is required.';
            }
            return true;
        }
        api(`/ajax/staff/customers/${id}`, {
            method: 'PATCH',
            body: JSON.stringify({ contact_number: phone, email: email || null }),
        })
            .then((r) => {
                hideModal('[data-edit-customer-modal]');
                openNotice('Customer updated', r.message || 'Contact info saved.');
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

    const laundryBtn = t.closest('[data-do-laundry]');
    if (laundryBtn) {
        e.preventDefault();
        goToTransactionsForCustomer(
            app,
            {
                id: Number(laundryBtn.dataset.doLaundry),
                name: laundryBtn.dataset.customerName || '',
                contact_number: laundryBtn.dataset.customerPhone || '',
            },
            update
        );
        return true;
    }

    return false;
}

export function handleCustomerSearch(t, app) {
    if (!t.matches('[data-customer-search]')) return false;
    clearTimeout(t._debounce);
    t._debounce = setTimeout(() => filterCustomers(t, app), 150);
    return true;
}

function filterCustomers(t, app) {
    const q = (t.value || '').toLowerCase().trim();
    let visible = 0;
    app.querySelectorAll('[data-customer-row]').forEach((row) => {
        const hay = [row.dataset.name, row.dataset.contact, row.dataset.email].filter(Boolean).join(' ');
        const match = !q || hay.includes(q);
        row.hidden = !match;
        if (match) visible++;
    });
    const box = app.querySelector('[data-customer-rows]');
    const total = app.querySelectorAll('[data-customer-row]').length;
    let none = box?.querySelector('[data-no-match]');
    if (box && total && !visible) {
        if (!none) {
            none = document.createElement('div');
            none.className = 'order-row';
            none.dataset.noMatch = '';
            none.innerHTML = '<span style="grid-column:1/-1;color:var(--staff-muted)">No customers match your search.</span>';
            box.appendChild(none);
        }
    } else if (none) {
        none.remove();
    }
    return true;
}
