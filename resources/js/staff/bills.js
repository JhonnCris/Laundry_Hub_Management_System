/**
 * Admin: Bills & Expenses (monthly checklist, partial payments, one-off expenses, bill templates)
 */
import { api, esc, money, paginate } from './core.js';

const CATEGORY = { utilities: 'Utilities', rent: 'Rent', tax: 'Tax', supplies: 'Supplies', repairs: 'Repairs', other: 'Other', stock: 'Stock' };
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const monthLabel = (ym) => `${MONTHS[Number(ym.slice(5, 7)) - 1]} ${ym.slice(0, 4)}`;
const dayLabel = (iso) => `${MONTHS[Number(iso.slice(5, 7)) - 1]} ${Number(iso.slice(8, 10))}`;
const shiftMonth = (ym, by) => {
    const d = new Date(Number(ym.slice(0, 4)), Number(ym.slice(5, 7)) - 1 + by, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

/** [label, css class] shown in the Status column. */
function statusBadge(s) {
    if (s.status === 'paid') return ['Paid', 'ready'];
    if (s.timing === 'overdue') return ['Overdue', 'is-bad'];
    if (s.status === 'no_amount') return ['Enter amount', 'pending'];
    if (s.timing === 'due_soon') return ['Due soon', 'processing'];
    return s.status === 'partial' ? ['Partial', 'processing'] : ['Unpaid', 'pending'];
}

function render(app, data) {
    const set = (sel, v) => {
        const el = app.querySelector(sel);
        if (el) el.textContent = v;
    };
    set('[data-bills-heading]', `Bills for ${monthLabel(data.month)}`);
    set('[data-bills-billed]', money(data.totals.billed));
    set('[data-bills-paid]', money(data.totals.paid));
    set('[data-bills-left]', money(data.totals.balance));
    set('[data-expenses-total]', `${money(data.totals.expenses)} spent in ${monthLabel(data.month)}`);
    const input = app.querySelector('[data-bills-month]');
    if (input) input.value = data.month;

    const rows = app.querySelector('[data-bill-rows]');
    rows.innerHTML = data.statements.length
        ? data.statements
              .map((s) => {
                  const [label, cls] = statusBadge(s);
                  const left = s.balance == null ? '—' : money(s.balance);
                  const action = s.status === 'paid' ? '<span></span>' : `<span class="user-actions"><button type="button" class="action-btn-primary" data-pay-bill="${s.id}">${s.status === 'no_amount' ? 'Enter & pay' : 'Pay'}</button></span>`;
                  return `<div class="order-row bill-row"><strong>${esc(s.name)}<small>${esc(CATEGORY[s.category] || s.category)}${s.payee ? ' · ' + esc(s.payee) : ''}</small></strong><span data-label="Due">${esc(dayLabel(s.due_date))}</span><span class="num" data-label="Billed">${s.amount_due == null ? '—' : money(s.amount_due)}</span><span class="num" data-label="Paid">${money(s.paid)}</span><span class="num" data-label="Left">${left}</span><em class="status ${cls}">${label}</em>${action}</div>`;
              })
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No bills for this month. Click Add bill to set one up.</span></div>';
    paginate(rows);

    const exp = app.querySelector('[data-expense-rows]');
    exp.innerHTML = data.expenses.length
        ? data.expenses
              .map((e) => `<div class="order-row exp-row"><span data-label="Date">${esc(dayLabel(e.date))}</span><strong>${esc(e.description)}</strong><span data-label="Category">${esc(CATEGORY[e.category] || e.category)}</span><span data-label="Ref">${esc(e.reference_no || '—')}</span><span class="num" data-label="Amount">${money(e.amount)}</span><span class="user-actions">${e.can_void ? `<button type="button" class="action-btn-danger" data-void-expense="${e.id}">Remove</button>` : ''}</span></div>`)
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No expenses recorded this month.</span></div>';
    paginate(exp);

    const tpl = app.querySelector('[data-bill-template-rows]');
    tpl.innerHTML = data.bills.length
        ? data.bills
              .map((b) => `<div class="order-row tpl-row"><strong>${esc(b.name)}</strong><span data-label="Payee">${esc(b.payee || '—')}</span><span data-label="Category">${esc(CATEGORY[b.category] || b.category)}</span><span class="num" data-label="Usual">${b.usual_amount == null ? 'Varies' : money(b.usual_amount)}</span><em class="status ${b.is_active ? 'ready' : 'is-bad'}">${b.is_active ? 'Active' : 'Stopped'}</em><span class="user-actions"><button type="button" class="action-btn-secondary" data-edit-bill="${b.id}">Edit</button></span></div>`)
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No bills yet.</span></div>';
    paginate(tpl);
}

export function loadBills(app, month) {
    const wanted = month || app._billsMonth || '';
    return api(`/ajax/admin/bills${wanted ? `?month=${wanted}` : ''}`)
        .then((data) => {
            app._bills = data;
            app._billsMonth = data.month;
            render(app, data);
        })
        .catch((ex) => {
            const rows = app.querySelector('[data-bill-rows]');
            if (rows) rows.innerHTML = `<div class="order-row"><span style="grid-column:1/-1;color:#af3b2c">${esc(ex.message)}</span></div>`;
        });
}

const q = (sel) => document.querySelector(sel);
const val = (sel) => q(sel)?.value ?? '';
const setVal = (sel, v) => {
    const el = q(sel);
    if (el) el.value = v ?? '';
};
const showError = (sel, message) => {
    const el = q(sel);
    if (!el) return;
    el.hidden = !message;
    el.textContent = message || '';
};

export function handleBillsClick(t, e, ctx) {
    const { app, showModal, hideModal, openNotice } = ctx;
    const refresh = () => Promise.all([loadBills(app), ctx.loadStaffBootstrap?.()]);

    const monthInput = app.querySelector('[data-bills-month]');
    if (monthInput && !monthInput._bound) {
        monthInput._bound = true;
        monthInput.addEventListener('change', () => monthInput.value && loadBills(app, monthInput.value));
    }
    if (t.closest('[data-bills-prev]') || t.closest('[data-bills-next]')) {
        e.preventDefault();
        loadBills(app, shiftMonth(app._billsMonth || today().slice(0, 7), t.closest('[data-bills-prev]') ? -1 : 1));
        return true;
    }

    // Pay a bill (all or part)
    const pay = t.closest('[data-pay-bill]');
    if (pay) {
        e.preventDefault();
        const s = (app._bills?.statements || []).find((x) => String(x.id) === pay.dataset.payBill);
        if (!s) return true;
        setVal('[data-pay-statement-id]', s.id);
        q('[data-pay-title]').textContent = `${s.name} · ${monthLabel(s.period_month)}`;
        q('[data-pay-hint]').textContent = s.amount_due == null
            ? 'Enter the amount on this month\'s bill, then how much you are paying now.'
            : `Billed ${money(s.amount_due)} · paid so far ${money(s.paid)} · left ${money(s.balance)}. You can pay in parts.`;
        setVal('[data-pay-billed]', s.amount_due ?? '');
        setVal('[data-pay-amount]', s.balance ?? '');
        setVal('[data-pay-date]', today());
        q('[data-pay-date]').max = today();
        setVal('[data-pay-reference]', '');
        setVal('[data-pay-notes]', '');
        showError('[data-pay-error]', '');
        showModal('[data-pay-modal]');
        return true;
    }
    if (t.closest('[data-close-pay]')) {
        e.preventDefault();
        hideModal('[data-pay-modal]');
        return true;
    }
    if (t.closest('[data-confirm-pay]')) {
        e.preventDefault();
        const amount = Number(val('[data-pay-amount]'));
        if (!(amount > 0)) return showError('[data-pay-error]', 'Enter how much you are paying now.'), true;
        const billed = val('[data-pay-billed]');
        const body = { amount, amount_due: billed === '' ? null : Number(billed), paid_on: val('[data-pay-date]') || null, reference_no: val('[data-pay-reference]').trim() || null, notes: val('[data-pay-notes]').trim() || null };
        api(`/ajax/admin/bill-statements/${val('[data-pay-statement-id]')}/payments`, { method: 'POST', body: JSON.stringify(body) })
            .then((r) => {
                hideModal('[data-pay-modal]');
                openNotice('Payment recorded', r.statement.status === 'paid' ? `${r.statement.name} is fully paid.` : `${money(r.statement.balance)} is still left on ${r.statement.name}.`, 'success');
                refresh();
            })
            .catch((ex) => showError('[data-pay-error]', ex.message));
        return true;
    }

    // Bill templates
    if (t.closest('[data-open-add-bill]') || t.closest('[data-edit-bill]')) {
        e.preventDefault();
        const id = t.closest('[data-edit-bill]')?.dataset.editBill;
        const b = id ? (app._bills?.bills || []).find((x) => String(x.id) === id) : null;
        q('[data-bill-modal-title]').textContent = b ? 'Edit bill' : 'Add bill';
        setVal('[data-bill-id]', b?.id);
        setVal('[data-bill-name]', b?.name);
        setVal('[data-bill-payee]', b?.payee);
        setVal('[data-bill-category]', b?.category || 'utilities');
        setVal('[data-bill-usual]', b?.usual_amount);
        setVal('[data-bill-active]', b && !b.is_active ? '0' : '1');
        showError('[data-bill-error]', '');
        showModal('[data-bill-modal]');
        return true;
    }
    if (t.closest('[data-close-bill]')) {
        e.preventDefault();
        hideModal('[data-bill-modal]');
        return true;
    }
    if (t.closest('[data-confirm-bill]')) {
        e.preventDefault();
        const id = val('[data-bill-id]');
        const usual = val('[data-bill-usual]');
        const body = { name: val('[data-bill-name]').trim(), payee: val('[data-bill-payee]').trim() || null, category: val('[data-bill-category]'), usual_amount: usual === '' ? null : Number(usual), is_active: val('[data-bill-active]') === '1' };
        if (!body.name) return showError('[data-bill-error]', 'Enter a name for the bill.'), true;
        api(id ? `/ajax/admin/bills/${id}` : '/ajax/admin/bills', { method: id ? 'PATCH' : 'POST', body: JSON.stringify(body) })
            .then(() => {
                hideModal('[data-bill-modal]');
                openNotice('Bill saved', `${body.name} was saved. It shows on this month's checklist.`, 'success');
                refresh();
            })
            .catch((ex) => showError('[data-bill-error]', ex.message));
        return true;
    }

    // One-off expense
    if (t.closest('[data-open-add-expense]')) {
        e.preventDefault();
        ['[data-exp-description]', '[data-exp-amount]', '[data-exp-reference]'].forEach((s) => setVal(s, ''));
        setVal('[data-exp-category]', 'supplies');
        setVal('[data-exp-date]', today());
        q('[data-exp-date]').max = today();
        showError('[data-exp-error]', '');
        showModal('[data-expense-modal]');
        return true;
    }
    if (t.closest('[data-close-expense]')) {
        e.preventDefault();
        hideModal('[data-expense-modal]');
        return true;
    }
    if (t.closest('[data-confirm-expense]')) {
        e.preventDefault();
        const body = { description: val('[data-exp-description]').trim(), category: val('[data-exp-category]'), amount: Number(val('[data-exp-amount]')), paid_on: val('[data-exp-date]') || null, reference_no: val('[data-exp-reference]').trim() || null };
        if (!body.description) return showError('[data-exp-error]', 'Describe what was paid for.'), true;
        if (!(body.amount > 0)) return showError('[data-exp-error]', 'Enter the amount paid.'), true;
        api('/ajax/admin/expenses', { method: 'POST', body: JSON.stringify(body) })
            .then(() => {
                hideModal('[data-expense-modal]');
                openNotice('Expense recorded', `${body.description} was added to this month's expenses.`, 'success');
                refresh();
            })
            .catch((ex) => showError('[data-exp-error]', ex.message));
        return true;
    }

    // Remove a mistaken entry
    const voidBtn = t.closest('[data-void-expense]');
    if (voidBtn) {
        e.preventDefault();
        setVal('[data-void-id]', voidBtn.dataset.voidExpense);
        setVal('[data-void-reason]', '');
        showError('[data-void-error]', '');
        showModal('[data-void-modal]');
        return true;
    }
    if (t.closest('[data-close-void]')) {
        e.preventDefault();
        hideModal('[data-void-modal]');
        return true;
    }
    if (t.closest('[data-confirm-void]')) {
        e.preventDefault();
        const reason = val('[data-void-reason]').trim();
        if (reason.length < 3) return showError('[data-void-error]', 'Please give a short reason (at least 3 letters).'), true;
        api(`/ajax/admin/expenses/${val('[data-void-id]')}/void`, { method: 'POST', body: JSON.stringify({ reason }) })
            .then((r) => {
                hideModal('[data-void-modal]');
                openNotice('Entry removed', r.message, 'success');
                refresh();
            })
            .catch((ex) => showError('[data-void-error]', ex.message));
        return true;
    }

    return false;
}
