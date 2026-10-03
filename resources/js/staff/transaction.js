    /**
 * Transaction module: load details, pricing, payment, receipt
 */
import { api, esc, money } from './core.js';
import { getSelectedCustomer, customerFullName } from './customers.js';

export function createTransaction(app, { showModal, hideModal, openNotice, loadStaffBootstrap }) {
    const activeWorkflow = () => {
        const open = app.querySelector('[data-workflow]:not([hidden])');
        return open || app.querySelector('[data-workflow="drop_off"]') || app.querySelector('[data-workflow]');
    };

    const setOutputValue = (output, value) => {
        if (!output) return;
        const v = String(Math.max(0, Number(value) || 0));
        output.value = v;
        output.textContent = v;
    };

    const getGarmentTotal = () => {
        let total = 0;
        app.querySelectorAll('[data-workflow="drop_off"] [data-garment-count]').forEach((el) => {
            total += Number(el.textContent || el.value || 0);
        });
        return total;
    };

    const getAddOns = () => {
        const items = [];
        app.querySelectorAll('.add-on').forEach((item) => {
            const out = item.querySelector('output');
            const qty = Number(out?.value || out?.textContent || 0);
            if (qty > 0) {
                items.push({
                    name: item.querySelector('strong')?.textContent?.trim() || 'Item',
                    qty,
                    price: Number(item.dataset.price || 0),
                    id: Number(item.dataset.itemId || 0),
                });
            }
        });
        return items;
    };

    const isSelfService = () =>
        app.querySelector('[data-service].is-selected')?.dataset?.service === 'self_service';

    const getConsumable = (workflow) => {
        if (!workflow) return { amount: 0, label: 'None', qty: 0 };
        const select = workflow.querySelector('[data-consumable]');
        const qtyInput = workflow.querySelector('[data-consumable-qty]');
        const unit = Number(select?.value || 0);
        const qty = Number(qtyInput?.value || 0);
        const optText = select?.selectedOptions?.[0]?.textContent?.trim() || 'None';
        const name = optText.split('·')[0].trim();
        return { amount: unit * qty, label: name, qty, unit };
    };

    const MIN_BILLABLE_KG = 3;

    const getLoadKg = (workflow) => {
        const input = workflow?.querySelector('[data-load-kg]');
        const raw = Number(input?.value);
        if (!input || input.value === '' || Number.isNaN(raw) || raw < 0) return 0;
        return raw;
    };

    const suggestDuration = (kg) => {
        if (kg <= 0) return null;
        if (kg < 4) return 30;
        if (kg < 7) return 45;
        if (kg < 10) return 60;
        if (kg < 13) return 75;
        return 90;
    };

    const getServiceCharge = (workflow, selfService) => {
        if (!workflow) return { amount: 0, label: '—', duration: null };
        const priceSelect = workflow.querySelector('[data-service-price]');
        const pricing = priceSelect?.dataset?.pricing;
        const rate = Number(priceSelect?.value || 0);
        const label = priceSelect?.selectedOptions?.[0]?.textContent?.trim() || '—';
        if (selfService || pricing === 'per_kg') {
            const kg = getLoadKg(workflow);
            const billable = Math.max(kg, kg > 0 ? MIN_BILLABLE_KG : 0);
            const durationSel = workflow.querySelector('[data-cycle-duration]');
            const duration = durationSel ? Number(durationSel.value || 0) : suggestDuration(kg);
            return {
                amount: rate * billable,
                label,
                duration,
                kg,
                rate,
            };
        }
        return { amount: rate, label, duration: null, kg: getLoadKg(workflow), rate };
    };

    const buildSummary = () => {
        const workflow = activeWorkflow();
        const selfService = isSelfService();
        const garments = getGarmentTotal();
        const addOns = getAddOns();
        const addOnTotal = addOns.reduce((s, i) => s + i.qty * i.price, 0);
        const consumable = getConsumable(workflow);
        const service = getServiceCharge(workflow, selfService);
        const hasLaundry = selfService
            ? service.kg > 0 || service.amount > 0
            : garments > 0 || service.amount > 0;
        const hasAddOns = addOnTotal > 0;
        const total = service.amount + consumable.amount + addOnTotal;
        return {
            selfService,
            hasLaundry,
            hasAddOns,
            garments,
            addOns,
            addOnTotal,
            consumable,
            service,
            serviceAmount: service.amount,
            kg: service.kg || 0,
            duration: service.duration,
            total,
            customer: customerFullName(app),
        };
    };

    const update = () => {
        const s = buildSummary();
        app._lastSummary = s;

        app.querySelectorAll('[data-summary-garments]').forEach((el) => {
            el.textContent = String(s.garments);
        });

        const title = app.querySelector('[data-summary-title]');
        if (title) {
            title.textContent = s.hasLaundry ? 'Service Summary' : s.hasAddOns ? 'Purchase Summary' : 'Summary';
        }

        const show = s.hasLaundry || s.hasAddOns;
        const toggle = (sel, visible) => {
            const el = app.querySelector(sel);
            if (el) el.hidden = !visible;
        };
        const set = (sel, val) => {
            app.querySelectorAll(sel).forEach((el) => {
                el.textContent = val;
            });
        };

        toggle('[data-summary-empty]', !show);
        toggle('[data-summary-service-block]', s.hasLaundry);
        toggle('[data-summary-items-block]', s.hasAddOns);
        toggle('[data-summary-total-block]', show);

        toggle('[data-row-basket]', !s.selfService);
        toggle('[data-row-garments]', !s.selfService);
        toggle('[data-row-kg]', s.kg > 0);
        toggle('[data-row-duration]', s.selfService && !!s.duration);
        toggle('[data-row-service-type]', true);
        toggle('[data-row-consumable]', s.consumable.qty > 0);

        set('[data-summary-tag]', app.querySelector('[data-basket-tag]')?.value || '—');
        set('[data-summary-service]', s.selfService ? 'Self Service' : 'Drop Off');
        set('[data-summary-kg]', s.kg ? `${s.kg} kg` : '—');
        set('[data-summary-duration]', s.duration ? `${s.duration} min` : '—');
        set('[data-summary-service-type]', s.service.label || '—');
        set('[data-summary-consumable]', s.consumable.qty > 0 ? `${s.consumable.label} × ${s.consumable.qty}` : '—');
        set('[data-total]', money(s.total));

        const itemsEl = app.querySelector('[data-summary-items]');
        if (itemsEl) {
            itemsEl.innerHTML = s.addOns
                .map((i) => `<div><dt>${esc(i.name)} × ${i.qty}</dt><dd>${money(i.qty * i.price)}</dd></div>`)
                .join('');
        }

        const preview = app.querySelector('[data-kg-preview]');
        if (preview) {
            preview.hidden = !(s.selfService && s.kg > 0);
            const strong = preview.querySelector('strong');
            if (strong) {
                const billable = Math.max(s.kg, MIN_BILLABLE_KG);
                strong.textContent = `${money(s.service.amount)} (${billable} kg × ${money(s.service.rate)})`;
            }
        }
        const hint = app.querySelector('[data-duration-hint]');
        if (hint) {
            const sel = app.querySelector('[data-workflow="self_service"] [data-cycle-duration]');
            hint.textContent = sel?.dataset.userSet ? 'Duration set manually.' : 'Suggested from weight when you enter kg.';
        }

        const saveBtn = app.querySelector('[data-save]');
        if (saveBtn) {
            saveBtn.disabled = !(s.hasLaundry || s.hasAddOns);
            saveBtn.textContent = s.hasLaundry ? 'Save Transaction' : 'Save Purchase';
        }
    };

    const resetTransactionForm = () => {
        app.querySelectorAll('[data-garment-count]').forEach((el) => {
            el.value = '0';
            el.textContent = '0';
        });
        app.querySelectorAll('.add-on output').forEach((el) => {
            el.value = '0';
            el.textContent = '0';
        });
        app.querySelectorAll('[data-load-kg]').forEach((el) => {
            el.value = '';
        });
        app.querySelectorAll('[data-consumable-qty]').forEach((el) => {
            el.value = '0';
        });
        app.querySelectorAll('[data-consumable]').forEach((el) => {
            el.value = '0';
        });
        const notes = app.querySelector('.notes-card textarea');
        if (notes) notes.value = '';
        app.querySelectorAll('[data-cycle-duration]').forEach((el) => {
            delete el.dataset.userSet;
            el.value = '45';
        });
        const basketTag = app.querySelector('[data-basket-tag]');
        if (basketTag) {
            const used = basketTag.value;
            const next = (app._bootstrap?.available_baskets || []).find((b) => b.code !== used);
            basketTag.value = next ? next.code : '';
        }
        app._pendingBasket = null;
        const grid = app.querySelector('[data-garment-grid]');
        if (grid) {
            grid.querySelectorAll('.garment-counter').forEach((card, idx) => {
                if (idx >= 3) card.remove();
            });
        }
        update();
    };

    const buildSavePayload = (s, cash) => {
        const workflow = activeWorkflow();
        const selfService = isSelfService();
        const garments = [];
        app.querySelectorAll('[data-workflow="drop_off"] .garment-counter').forEach((card) => {
            const name = card.querySelector('span')?.textContent?.trim();
            const qty = Number(card.querySelector('[data-garment-count]')?.textContent || 0);
            if (name && qty > 0) garments.push({ name, quantity: qty });
        });
        const items = [];
        app.querySelectorAll('.add-on').forEach((el) => {
            const qty = Number(el.querySelector('output')?.textContent || 0);
            const id = Number(el.dataset.itemId || 0);
            if (qty > 0 && id) items.push({ inventory_item_id: id, quantity: qty });
        });
        const consumableSelect = workflow?.querySelector('[data-consumable]');
        const detergentItemId = Number(consumableSelect?.selectedOptions?.[0]?.dataset?.itemId || 0) || null;
        const detergentQty = Number(workflow?.querySelector('[data-consumable-qty]')?.value || 0);
        const serviceSelect = workflow?.querySelector('[data-service-price]');
        const serviceId = Number(serviceSelect?.selectedOptions?.[0]?.dataset?.serviceId || 0) || null;
        const machineId =
            Number(workflow?.querySelector('[data-machine]')?.selectedOptions?.[0]?.dataset?.machineId || 0) || null;
        const customer = getSelectedCustomer(app);
        return {
            customer_id: customer?.id || null,
            transaction_type: selfService ? 'self_service' : 'drop_off',
            basket_code: selfService ? null : app.querySelector('[data-basket-tag]')?.value || null,
            service_id: serviceId,
            service_amount: s.serviceAmount || 0,
            machine_id: selfService ? machineId : null,
            load_weight_kg: s.kg || null,
            cycle_minutes: s.duration ? Number(s.duration) : null,
            detergent_item_id: detergentItemId,
            detergent_quantity: detergentQty,
            garments,
            items,
            notes: (app.querySelector('.notes-card textarea')?.value || '').trim(),
            cash_tendered: cash,
            total_amount: s.total,
            client_token: app._payToken || null,
        };
    };

    const buildReceiptHtml = (s) => {
        const plain = (t) => String(t || '').split('·')[0].trim();
        const when = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
        const servedBy = app._bootstrap?.user?.name || '—';
        const rows = [];
        const line = (label, amount) => rows.push(`<tr><td>${esc(label)}</td><td>${amount == null ? '' : money(amount)}</td></tr>`);
        const detail = (text) => rows.push(`<tr class="receipt-detail"><td colspan="2">${esc(text)}</td></tr>`);

        if (s.hasLaundry) {
            line(`${s.selfService ? 'Self Service' : 'Drop Off'} – ${plain(s.service.label)}`, s.serviceAmount);
            if (s.kg) detail(`Load: ${s.kg} kg`);
            if (!s.selfService && s.garments) detail(`Garments: ${s.garments} pcs`);
            if (s.selfService && s.duration) detail(`Cycle: ${s.duration} min`);
        }
        if (s.consumable.qty > 0) {
            line(`${plain(s.consumable.label)} × ${s.consumable.qty}`, s.consumable.amount);
        }
        s.addOns.forEach((i) => line(`${i.name} × ${i.qty}`, i.qty * i.price));

        return `<div class="receipt">
            <div class="receipt-brand"><strong>SSK Laba Dami</strong><small>Laundry Hub · Official receipt</small></div>
            <hr>
            <dl class="receipt-meta-grid">
                <dt>Receipt no.</dt><dd>${s.receiptId ? '#' + esc(s.receiptId) : '—'}</dd>
                <dt>Date</dt><dd>${esc(when)}</dd>
                <dt>Customer</dt><dd>${esc(s.customer || '—')}</dd>
                <dt>Served by</dt><dd>${esc(servedBy)}</dd>
            </dl>
            <hr>
            <table class="receipt-lines">${rows.join('')}</table>
            <hr>
            <table class="receipt-totals">
                <tr class="receipt-grand"><td>Total</td><td>${money(s.total)}</td></tr>
                <tr><td>Cash received</td><td>${money(s.cashReceived)}</td></tr>
                <tr><td>Change</td><td>${money(s.change)}</td></tr>
            </table>
            ${
                s.notifyUrl && s.hasLaundry
                    ? `<div class="receipt-notify"><img alt="QR code" width="110" height="110" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=${encodeURIComponent(s.notifyUrl)}"><small>Scan to get a phone notification when your laundry is ready.</small></div>`
                    : ''
            }
            <p class="receipt-footer">Thank you for choosing SSK Laba Dami!</p>
        </div>`;
    };

    const recalcPaymentChange = () => {
        const s = app._lastSummary;
        const cashInput = document.querySelector('[data-payment-cash]');
        const changeEl = document.querySelector('[data-payment-change]');
        const err = document.querySelector('[data-payment-error]');
        const btn = document.querySelector('[data-confirm-payment]');
        if (!s || !cashInput) return;
        const cash = Number(cashInput.value);
        const due = Number(s.total);
        const ok = !Number.isNaN(cash) && cash + 1e-9 >= due;
        if (changeEl) changeEl.textContent = ok ? money(cash - due) : '—';
        const changeRow = document.querySelector('[data-payment-change-row]');
        if (changeRow) changeRow.hidden = !ok;
        if (err) {
            err.hidden = ok || cashInput.value === '';
            err.textContent = ok || cashInput.value === '' ? '' : 'Cash is less than total due.';
        }
        if (btn) btn.disabled = !ok;
    };

    const openPaymentModal = () => {
        const s = app._lastSummary;
        if (!s) return;
        const due = document.querySelector('[data-payment-due]');
        if (due) due.value = money(s.total);
        const summaryEl = document.querySelector('[data-payment-summary]');
        if (summaryEl) {
            const rows = [];
            if (s.customer) rows.push(`<div><strong>Customer</strong><span>${esc(s.customer)}</span></div>`);
            if (s.hasLaundry) {
                rows.push(`<div><strong>Service</strong><span>${s.selfService ? 'Self Service' : 'Drop Off'} · ${esc(s.service.label)}</span></div>`);
            }
            if (s.consumable.qty > 0) {
                rows.push(`<div><strong>${esc(s.consumable.label)} × ${s.consumable.qty}</strong><span>${money(s.consumable.amount)}</span></div>`);
            }
            s.addOns.forEach((i) => {
                rows.push(`<div><strong>${esc(i.name)} × ${i.qty}</strong><span>${money(i.qty * i.price)}</span></div>`);
            });
            summaryEl.innerHTML = rows.join('');
        }
        const changeRow = document.querySelector('[data-payment-change-row]');
        if (changeRow) changeRow.hidden = true;
        const cash = document.querySelector('[data-payment-cash]');
        if (cash) cash.value = '';
        const err = document.querySelector('[data-payment-error]');
        if (err) err.hidden = true;
        const btn = document.querySelector('[data-confirm-payment]');
        if (btn) btn.disabled = true;
        app._payToken = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random();
        const changeEl = document.querySelector('[data-payment-change]');
        if (changeEl) changeEl.textContent = '—';
        showModal('[data-payment-modal]');
    };

    const confirmPayment = async () => {
        const s = app._lastSummary;
        const cashInput = document.querySelector('[data-payment-cash]');
        if (!s || !cashInput) return;
        const cash = Number(cashInput.value);
        const due = Number(s.total);
        if (Number.isNaN(cash) || cash + 1e-9 < due) {
            recalcPaymentChange();
            return;
        }
        const confirmBtn = document.querySelector('[data-confirm-payment]');
        if (confirmBtn) confirmBtn.disabled = true;
        try {
            const payload = buildSavePayload(s, cash);
            const result = await api('/api/staff/transactions', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            s.cashReceived = cash;
            s.change = result.change_given ?? cash - due;
            if (result.transaction?.id) s.receiptId = result.transaction.id;
            s.notifyUrl = result.notify_url || null;
            hideModal('[data-payment-modal]');
            const body = document.querySelector('[data-receipt-body]');
            if (body) body.innerHTML = buildReceiptHtml(s);
            showModal('[data-receipt-modal]');
            const notice = app.querySelector('[data-save-notice]');
            if (notice) {
                notice.textContent = s.hasLaundry
                    ? `Saved #${result.transaction?.id || ''} · paid · change ${money(s.change)}`
                    : `Purchase saved · change ${money(s.change)}`;
                notice.classList.remove('is-error');
            }
            resetTransactionForm();
            if (typeof loadStaffBootstrap === 'function') loadStaffBootstrap();
            if (confirmBtn) confirmBtn.disabled = false;
        } catch (err) {
            const payErr = document.querySelector('[data-payment-error]');
            if (payErr) {
                payErr.hidden = false;
                payErr.textContent = err.message || 'Could not save transaction.';
            }
            if (confirmBtn) confirmBtn.disabled = false;
        }
    };

    const handleSaveClick = () => {
        const notice = app.querySelector('[data-save-notice]');
        const customer = getSelectedCustomer(app);
        const s = app._lastSummary;
        if (!customer || !customer.id) {
            if (notice) {
                notice.textContent = 'Select a customer first (Manage Customer → Do Laundry).';
                notice.classList.add('is-error');
            }
            return;
        }
        if (!s || (!s.hasLaundry && !s.hasAddOns)) {
            if (notice) {
                notice.textContent = isSelfService()
                    ? 'Enter load weight (kg) or add snacks before saving.'
                    : 'Add garments or snacks before saving.';
                notice.classList.add('is-error');
            }
            return;
        }
        if (s.selfService && !(s.kg > 0)) {
            if (notice) {
                notice.textContent = 'Enter load weight in kg for self-service pricing.';
                notice.classList.add('is-error');
            }
            return;
        }
        if (notice) {
            notice.textContent = '';
            notice.classList.remove('is-error');
        }
        openPaymentModal();
    };

    /** Transaction UI clicks (garments, service toggle, basket, payment, etc.) */
    const handleTransactionClick = (t, e) => {
        const serviceBtn = t.closest('[data-service]');
        if (serviceBtn) {
            e.preventDefault();
            app.querySelectorAll('[data-service]').forEach((item) => {
                item.classList.toggle('is-selected', item === serviceBtn);
            });
            const mode = serviceBtn.dataset.service;
            app.querySelectorAll('[data-workflow]').forEach((w) => {
                w.hidden = w.dataset.workflow !== mode;
            });
            update();
            return true;
        }

        if (t.closest('[data-garment-increase]') || t.closest('[data-garment-decrease]')) {
            e.preventDefault();
            const card = t.closest('.garment-counter');
            const out = card?.querySelector('[data-garment-count]');
            if (!out) return true;
            const delta = t.closest('[data-garment-increase]') ? 1 : -1;
            setOutputValue(out, Number(out.textContent || 0) + delta);
            update();
            return true;
        }

        if (t.closest('[data-add-garment]') || t.closest('[data-open-garment-modal]')) {
            e.preventDefault();
            showModal('[data-garment-modal]');
            return true;
        }
        if (t.closest('[data-close-garment-modal]')) {
            e.preventDefault();
            hideModal('[data-garment-modal]');
            return true;
        }
        if (t.closest('[data-confirm-garment]')) {
            e.preventDefault();
            const nameInput = document.querySelector('[data-new-garment-name]');
            const qtyInput = document.querySelector('[data-new-garment-qty]');
            const err = document.querySelector('[data-garment-modal-error]');
            const name = (nameInput?.value || '').trim();
            const qty = Math.max(0, Number(qtyInput?.value || 0));
            if (!name) {
                if (err) {
                    err.hidden = false;
                    err.textContent = 'Enter a garment type name.';
                }
                return true;
            }
            const grid = app.querySelector('[data-garment-grid]');
            if (grid) {
                const card = document.createElement('div');
                card.className = 'garment-counter';
                card.innerHTML = `<span>${esc(name)}</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>${qty}</output><button data-garment-increase type="button">+</button></div>`;
                const addBtn = grid.querySelector('.add-garment, [data-add-garment]');
                if (addBtn) grid.insertBefore(card, addBtn);
                else grid.appendChild(card);
            }
            hideModal('[data-garment-modal]');
            if (nameInput) nameInput.value = '';
            if (qtyInput) qtyInput.value = '0';
            update();
            return true;
        }

        if (t.closest('[data-reassign-basket]')) {
            e.preventDefault();
            const picker = app.querySelector('[data-basket-picker]');
            if (picker) picker.hidden = false;
            return true;
        }
        if (t.closest('[data-basket-cancel]')) {
            e.preventDefault();
            const picker = app.querySelector('[data-basket-picker]');
            if (picker) picker.hidden = true;
            return true;
        }
        const basketOpt = t.closest('[data-basket-option]');
        if (basketOpt) {
            e.preventDefault();
            app._pendingBasket = basketOpt.dataset.basketOption;
            app.querySelectorAll('[data-basket-option]').forEach((b) => b.classList.remove('is-selected'));
            basketOpt.classList.add('is-selected');
            const conf = app.querySelector('[data-basket-confirm]');
            if (conf) conf.disabled = false;
            return true;
        }
        if (t.closest('[data-basket-confirm]')) {
            e.preventDefault();
            if (app._pendingBasket) {
                const tag = app.querySelector('[data-basket-tag]');
                if (tag) tag.value = app._pendingBasket;
            }
            const picker = app.querySelector('[data-basket-picker]');
            if (picker) picker.hidden = true;
            update();
            return true;
        }

        // add-on qty
        if (t.closest('.add-on') && (t.matches('button') || t.closest('button'))) {
            const row = t.closest('.add-on');
            const out = row?.querySelector('output');
            if (out && (t.textContent.includes('+') || t.textContent.includes('−') || t.textContent.includes('-'))) {
                e.preventDefault();
                const delta = t.textContent.includes('+') ? 1 : -1;
                setOutputValue(out, Number(out.textContent || 0) + delta);
                update();
                return true;
            }
        }

        const cInc = t.closest('[data-consumable-increase]');
        const cDec = t.closest('[data-consumable-decrease]');
        if (cInc || cDec) {
            e.preventDefault();
            const wrap = t.closest('label') || t.closest('.form-grid') || app;
            const input = wrap?.querySelector('[data-consumable-qty]');
            if (input) {
                input.value = String(Math.max(0, Number(input.value || 0) + (cInc ? 1 : -1)));
                update();
            }
            return true;
        }

        if (t.closest('[data-clear-items]')) {
            e.preventDefault();
            app.querySelectorAll('.add-on output').forEach((o) => setOutputValue(o, 0));
            update();
            return true;
        }

        if (t.closest('[data-save]')) {
            e.preventDefault();
            handleSaveClick();
            return true;
        }
        if (t.closest('[data-close-payment]')) {
            e.preventDefault();
            hideModal('[data-payment-modal]');
            return true;
        }
        if (t.closest('[data-confirm-payment]')) {
            e.preventDefault();
            confirmPayment();
            return true;
        }
        if (t.closest('[data-close-receipt]')) {
            e.preventDefault();
            hideModal('[data-receipt-modal]');
            return true;
        }
        if (t.closest('[data-print-receipt]')) {
            e.preventDefault();
            window.print();
            return true;
        }
        return false;
    };

    const handleTransactionInput = (t) => {
        if (t.matches('[data-consumable-qty]') && t.value !== '') {
            const n = Math.floor(Number(t.value));
            t.value = String(Number.isFinite(n) ? Math.min(99, Math.max(0, n)) : 0);
        }
        if (
            t.matches('[data-load-kg]') ||
            t.matches('[data-consumable]') ||
            t.matches('[data-consumable-qty]') ||
            t.matches('[data-service-price]') ||
            t.matches('[data-cycle-duration]')
        ) {
            const workflow = t.closest('[data-workflow]');
            const durationSel = workflow?.querySelector('[data-cycle-duration]');
            if (t.matches('[data-cycle-duration]')) {
                t.dataset.userSet = '1';
            } else if (t.matches('[data-load-kg]') && durationSel && !durationSel.dataset.userSet) {
                const suggested = suggestDuration(getLoadKg(workflow));
                if (suggested) durationSel.value = String(suggested);
            }
            update();
            return true;
        }
        if (t.matches('[data-payment-cash]')) {
            recalcPaymentChange();
            return true;
        }
        return false;
    };

    return {
        update,
        resetTransactionForm,
        handleTransactionClick,
        handleTransactionInput,
        activeWorkflow,
    };
}
