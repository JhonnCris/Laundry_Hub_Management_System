    /**
 * Transaction module: load details, pricing, payment, receipt
 */
import { api, esc, money, shopInfo } from './core.js';
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

    const WASH_MINUTES = 38;
    const SIZE_LABEL = { giant: 'Giant', titan: 'Titan' };
    const rates = () => app._bootstrap?.machine_rates || [];
    const machines = () => app._bootstrap?.machines || [];
    const rateFor = (size, kind, minutes = 0) =>
        rates().find((r) => r.size === size && r.kind === kind && Number(r.minutes) === Number(minutes));
    const dryMinutesFor = (size) =>
        rates()
            .filter((r) => r.size === size && r.kind === 'dry')
            .map((r) => Number(r.minutes))
            .sort((a, b) => a - b);
    const picked = () => (app._picked = app._picked || new Map());

    /** Selected machines that are still free, each with its time and price. */
    const pickedLines = () => {
        const lines = [];
        picked().forEach((minutes, id) => {
            const m = machines().find((x) => x.id === id && x.status === 'available');
            if (!m) return;
            if (m.type === 'washer') {
                const r = rateFor(m.size, 'wash');
                lines.push({ id, name: m.name, type: 'washer', size: m.size, minutes: WASH_MINUTES, price: Number(r?.price || 0), capacity: Number(r?.capacity_kg || 0) });
            } else {
                const mins = dryMinutesFor(m.size).includes(minutes) ? minutes : dryMinutesFor(m.size)[0];
                lines.push({ id, name: m.name, type: 'dryer', size: m.size, minutes: mins, price: Number(rateFor(m.size, 'dry', mins)?.price || 0), capacity: 0 });
            }
        });
        return lines;
    };

    const machineSummaryText = (lines) => {
        const w = lines.filter((l) => l.type === 'washer').length;
        const d = lines.length - w;
        return [w ? `${w} washer${w > 1 ? 's' : ''}` : '', d ? `${d} dryer${d > 1 ? 's' : ''}` : ''].filter(Boolean).join(' · ');
    };

    const renderMachinePicker = () => {
        const box = app.querySelector('[data-machine-picker]');
        if (!box) return;
        app._pickerFor = app._bootstrap;
        const list = machines();
        const free = new Set(list.filter((m) => m.status === 'available').map((m) => m.id));
        picked().forEach((_, id) => {
            if (!free.has(id)) picked().delete(id);
        });
        if (!list.length) {
            box.innerHTML = '<p class="dash-empty">No machines set up yet. Ask the admin to add machines.</p>';
            return;
        }
        const tile = (m) => {
            const isFree = m.status === 'available';
            const on = isFree && picked().has(m.id);
            const r = m.type === 'washer' ? rateFor(m.size, 'wash') : null;
            const info = `${SIZE_LABEL[m.size] || m.size}${r?.capacity_kg ? ` · up to ${Number(r.capacity_kg)} kg` : ' dryer'}`;
            const price = m.type === 'washer' ? `${money(r?.price)} · ${WASH_MINUTES} min` : on ? 'Choose time below' : 'Priced by time';
            const state = isFree ? (on ? 'Selected ✓' : 'Tap to add') : m.status.replace(/_/g, ' ');
            const minutes = on && m.type === 'dryer'
                ? `<select data-pick-minutes="${m.id}" aria-label="Drying time for ${esc(m.name)}">${dryMinutesFor(m.size)
                      .map((min) => `<option value="${min}"${min === (pickedLines().find((l) => l.id === m.id)?.minutes) ? ' selected' : ''}>${min} min · ${money(rateFor(m.size, 'dry', min)?.price)}</option>`)
                      .join('')}</select>`
                : '';
            return `<div class="machine-tile${on ? ' is-selected' : ''}${isFree ? '' : ' is-busy'}"><button type="button" data-pick-machine="${m.id}" aria-pressed="${on}" ${isFree ? '' : 'disabled'}><strong>${esc(m.name)}</strong><small>${esc(info)}</small><span class="tile-price">${esc(price)}</span><em>${esc(state)}</em></button>${minutes}</div>`;
        };
        box.innerHTML = [['washer', 'Washers'], ['dryer', 'Dryers']]
            .map(([type, label]) => {
                const tiles = list.filter((m) => m.type === type).map(tile).join('');
                return `<div class="machine-group"><h3>${label}</h3><div class="machine-tiles">${tiles || '<p class="dash-empty">None set up.</p>'}</div></div>`;
            })
            .join('');
    };

    const getLoadKg = (workflow) => {
        const input = workflow?.querySelector('[data-load-kg]');
        const raw = Number(input?.value);
        if (!input || input.value === '' || Number.isNaN(raw) || raw < 0) return 0;
        return raw;
    };

    const getServiceCharge = (workflow, selfService) => {
        if (!workflow) return { amount: 0, label: '—', duration: null };
        if (selfService) {
            const lines = pickedLines();
            return {
                amount: lines.reduce((sum, l) => sum + l.price, 0),
                label: machineSummaryText(lines) || '—',
                duration: lines.length ? Math.max(...lines.map((l) => l.minutes)) : null,
                kg: getLoadKg(workflow),
                machines: lines,
            };
        }
        const priceSelect = workflow.querySelector('[data-service-price]');
        const rate = Number(priceSelect?.value || 0);
        const label = priceSelect?.selectedOptions?.[0]?.textContent?.trim() || '—';
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
            ? (service.machines || []).length > 0
            : garments > 0 || service.amount > 0;
        const payLater = !selfService && hasLaundry && !!app.querySelector('[data-pay-later]')?.checked;
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
            machines: service.machines || [],
            payLater,
            total,
            customer: customerFullName(app),
        };
    };

    const update = () => {
        if (app._pickerFor !== app._bootstrap) renderMachinePicker();
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
        toggle('[data-row-duration]', s.selfService && s.machines.length > 0);
        toggle('[data-row-service-type]', !s.selfService && s.service.amount > 0);
        toggle('[data-row-consumable]', s.consumable.qty > 0);

        set('[data-summary-tag]', app.querySelector('[data-basket-tag]')?.value || '—');
        set('[data-summary-service]', s.selfService ? 'Self Service' : 'Drop Off');
        set('[data-summary-kg]', s.kg ? `${s.kg} kg` : '—');
        set('[data-summary-duration]', s.machines.length ? machineSummaryText(s.machines) : '—');
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
            preview.hidden = !(s.selfService && s.machines.length);
            const strong = preview.querySelector('strong');
            if (strong) {
                strong.textContent = `${money(s.service.amount)} (${s.machines.map((l) => `${l.name} ${l.minutes} min ${money(l.price)}`).join(' + ')})`;
            }
        }
        const bar = app.querySelector('[data-machine-total]');
        if (bar) {
            bar.hidden = !(s.selfService && s.machines.length);
            bar.innerHTML = `<span>${esc(machineSummaryText(s.machines))}</span><strong>${money(s.service.amount)}</strong>`;
        }
        const hint = app.querySelector('[data-capacity-hint]');
        if (hint) {
            const cap = s.machines.reduce((sum, l) => sum + l.capacity, 0);
            hint.classList.toggle('is-error', cap > 0 && s.kg > cap);
            hint.textContent = cap > 0 && s.kg > cap
                ? `Heavier than the selected washers hold (${cap} kg). Add another washer or lighten the load.`
                : cap > 0 ? `Selected washers hold up to ${cap} kg.` : 'Price is per machine, not per kg.';
        }

        const saveBtn = app.querySelector('[data-save]');
        if (saveBtn) {
            saveBtn.disabled = !(s.hasLaundry || s.hasAddOns);
            saveBtn.textContent = s.payLater ? 'Save (pay on release)' : s.hasLaundry ? 'Save Transaction' : 'Save Purchase';
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
        picked().clear();
        const later = app.querySelector('[data-pay-later]');
        if (later) later.checked = false;
        renderMachinePicker();
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
        const customer = getSelectedCustomer(app);
        return {
            customer_id: customer?.id || null,
            transaction_type: selfService ? 'self_service' : 'drop_off',
            basket_code: selfService ? null : app.querySelector('[data-basket-tag]')?.value || null,
            service_id: serviceId,
            service_amount: s.serviceAmount || 0,
            machines: selfService ? s.machines.map((l) => ({ machine_id: l.id, minutes: l.minutes })) : [],
            pay_later: !!s.payLater,
            load_weight_kg: s.kg || null,
            detergent_item_id: detergentItemId,
            detergent_quantity: detergentQty,
            garments,
            items,
            notes: (app.querySelector('.notes-card textarea')?.value || '').trim(),
            cash_tendered: s.payLater ? null : cash,
            total_amount: s.total,
            client_token: app._payToken || null,
        };
    };

    const buildReceiptHtml = (s) => {
        const shop = shopInfo(app);
        const plain = (t) => String(t || '').split('·')[0].trim();
        const createdAt = new Date(s.createdAt || Date.now());
        const when = createdAt.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
        const servedBy = app._bootstrap?.user?.name || '—';
        const readyAt = new Date(createdAt);
        readyAt.setDate(readyAt.getDate() + 1);
        readyAt.setHours(17, 0, 0, 0);
        const rows = [];
        const line = (label, amount) => rows.push(`<tr><td>${esc(label)}</td><td>${amount == null ? '' : money(amount)}</td></tr>`);
        const detail = (text) => rows.push(`<tr class="receipt-detail"><td colspan="2">${esc(text)}</td></tr>`);

        if (s.hasLaundry) {
            if (s.selfService) {
                s.machines.forEach((l) => line(`${l.name} (${SIZE_LABEL[l.size] || l.size}) · ${l.minutes} min`, l.price));
                detail(`Machines used: ${machineSummaryText(s.machines)}`);
            } else {
                line(`Drop Off – ${plain(s.service.label)}`, s.serviceAmount);
            }
            if (s.kg) detail(`Load: ${s.kg} kg`);
            if (!s.selfService && s.garments) detail(`Garments: ${s.garments} pcs`);
        }
        if (s.consumable.qty > 0) {
            line(`${plain(s.consumable.label)} × ${s.consumable.qty}`, s.consumable.amount);
        }
        s.addOns.forEach((i) => line(`${i.name} × ${i.qty}`, i.qty * i.price));

        return `<div class="receipt">
            <div class="receipt-brand"><strong>${esc(shop.name)}</strong><small>${esc(shop.address || shop.tagline)}</small></div>
            <hr>
            <div class="receipt-order"><strong>Order #${s.receiptId ? esc(s.receiptId) : '—'}</strong><span>${esc(when)}</span></div>
            <div class="receipt-meta-grid"><span>Customer</span><strong>${esc(s.customer || '—')}</strong><span>Cashier</span><strong>${esc(servedBy)}</strong></div>
            <hr>
            <table class="receipt-lines">${rows.join('')}</table>
            <hr>
            <table class="receipt-totals">
                <tr class="receipt-grand"><td>Total</td><td>${money(s.total)}</td></tr>
                ${s.payLater ? '' : `<tr><td>Cash</td><td>${money(s.cashReceived)}</td></tr><tr><td>Change</td><td>${money(s.change)}</td></tr>`}
            </table>
            <div class="receipt-paid">${s.payLater ? 'UNPAID – pay on release' : 'PAID'}</div>
            ${s.hasLaundry && !s.selfService ? `<div class="receipt-ready"><strong>Ready: ${esc(readyAt.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }))}</strong><span>Please bring this receipt at pickup and claim within ${shop.claimDays} days.</span></div>` : ''}
            <p class="receipt-footer">Thank you for choosing ${esc(shop.name)}!</p>
            ${s.notifyUrl && s.hasLaundry && !s.selfService ? `<div class="receipt-notify"><img alt="QR code" width="110" height="110" loading="lazy" decoding="async" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=${encodeURIComponent(s.notifyUrl)}"><small>Scan for a pickup notification.</small></div>` : ''}
        </div>`;
    };

    const payDue = () => (app._payMode === 'claim' ? Number(app._claim?.total || 0) : Number(app._lastSummary?.total || 0));

    const recalcPaymentChange = () => {
        const cashInput = document.querySelector('[data-payment-cash]');
        const changeEl = document.querySelector('[data-payment-change]');
        const err = document.querySelector('[data-payment-error]');
        const btn = document.querySelector('[data-confirm-payment]');
        if (!cashInput) return;
        if (app._payMode === 'later') {
            if (btn) btn.disabled = false;
            return;
        }
        const cash = Number(cashInput.value);
        const due = payDue();
        const ok = !Number.isNaN(cash) && cashInput.value !== '' && cash + 1e-9 >= due;
        if (changeEl) changeEl.textContent = ok ? money(cash - due) : '—';
        const changeRow = document.querySelector('[data-payment-change-row]');
        if (changeRow) changeRow.hidden = !ok;
        if (err) {
            err.hidden = ok || cashInput.value === '';
            err.textContent = ok || cashInput.value === '' ? '' : 'Cash is less than total due.';
        }
        if (btn) btn.disabled = !ok;
    };

    /** mode: 'new' (cash now) | 'later' (save unpaid) | 'claim' (collect when releasing). */
    const openPaymentModal = (mode = 'new') => {
        const s = app._lastSummary;
        if (mode !== 'claim' && !s) return;
        app._payMode = mode;
        const text = (sel, v) => {
            const el = document.querySelector(sel);
            if (el) el.textContent = v;
        };
        text('[data-payment-title]', { new: 'Record payment', later: 'Save without payment', claim: 'Collect payment' }[mode]);
        text('[data-payment-hint]', {
            new: 'Collect cash from the customer, then confirm to issue a receipt.',
            later: 'No cash is taken now. The customer pays when the laundry is released, and it cannot be released until it is paid.',
            claim: 'This laundry is unpaid. Collect the cash now, then it will be released to the customer.',
        }[mode]);
        const confirmBtn = document.querySelector('[data-confirm-payment]');
        if (confirmBtn) confirmBtn.textContent = { new: 'Confirm payment', later: 'Save order', claim: 'Collect & release' }[mode];
        const cashField = document.querySelector('[data-payment-cash-field]');
        if (cashField) cashField.hidden = mode === 'later';
        const due = document.querySelector('[data-payment-due]');
        if (due) due.value = money(payDue());
        const summaryEl = document.querySelector('[data-payment-summary]');
        if (summaryEl) {
            const rows = [];
            if (mode === 'claim') {
                rows.push(`<div><strong>Order #${esc(app._claim.id)}</strong><span>${money(app._claim.total)}</span></div>`);
            } else {
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
            }
            summaryEl.innerHTML = rows.join('');
        }
        const changeRow = document.querySelector('[data-payment-change-row]');
        if (changeRow) changeRow.hidden = true;
        const cash = document.querySelector('[data-payment-cash]');
        if (cash) cash.value = '';
        const err = document.querySelector('[data-payment-error]');
        if (err) err.hidden = true;
        if (confirmBtn) confirmBtn.disabled = mode !== 'later';
        app._payToken = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random();
        const changeEl = document.querySelector('[data-payment-change]');
        if (changeEl) changeEl.textContent = '—';
        showModal('[data-payment-modal]');
    };

    const showPayError = (message) => {
        const payErr = document.querySelector('[data-payment-error]');
        if (payErr) {
            payErr.hidden = false;
            payErr.textContent = message;
        }
    };

    /** Collect payment for an unpaid order as it is released to the customer. */
    const confirmClaim = async () => {
        const cashInput = document.querySelector('[data-payment-cash]');
        const cash = Number(cashInput?.value);
        const due = payDue();
        if (Number.isNaN(cash) || cash + 1e-9 < due) return recalcPaymentChange();
        const confirmBtn = document.querySelector('[data-confirm-payment]');
        if (confirmBtn) confirmBtn.disabled = true;
        try {
            const r = await api(`/ajax/staff/transactions/${app._claim.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: 'claimed', cash_tendered: cash }) });
            hideModal('[data-payment-modal]');
            openNotice('Paid and released', `Order #${app._claim.id} is paid. Change ${money(r.change_given ?? cash - due)}.`, 'success');
            if (typeof loadStaffBootstrap === 'function') loadStaffBootstrap();
        } catch (err) {
            showPayError(err.message || 'Could not release this order.');
            if (confirmBtn) confirmBtn.disabled = false;
        }
    };

    const collectOnRelease = (id, total) => {
        app._claim = { id, total: Number(total) };
        openPaymentModal('claim');
    };

    const confirmPayment = async () => {
        if (app._payMode === 'claim') return confirmClaim();
        const s = app._lastSummary;
        const cashInput = document.querySelector('[data-payment-cash]');
        if (!s || !cashInput) return;
        const later = app._payMode === 'later';
        const cash = later ? 0 : Number(cashInput.value);
        const due = Number(s.total);
        if (!later && (Number.isNaN(cash) || cash + 1e-9 < due)) {
            recalcPaymentChange();
            return;
        }
        const confirmBtn = document.querySelector('[data-confirm-payment]');
        if (confirmBtn) confirmBtn.disabled = true;
        try {
            const payload = buildSavePayload(s, cash);
            const result = await api('/ajax/staff/transactions', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            s.cashReceived = cash;
            s.change = later ? 0 : result.change_given ?? cash - due;
            if (result.transaction?.id) s.receiptId = result.transaction.id;
            s.createdAt = result.transaction?.created_at || new Date().toISOString();
            app._receiptTransactionId = s.receiptId;
            app._receiptTotal = s.total;
            app._receiptCustomerEmail = result.transaction?.customer?.email || '';
            s.notifyUrl = result.notify_url || null;
            hideModal('[data-payment-modal]');
            const body = document.querySelector('[data-receipt-body]');
            if (body) body.innerHTML = buildReceiptHtml(s);
            const newTransactionButton = app.querySelector('[data-new-tx-same-customer]');
            if (newTransactionButton) newTransactionButton.hidden = false;
            const emailButton = app.querySelector('[data-send-receipt-email]');
            if (emailButton) emailButton.hidden = !app._receiptCustomerEmail;
            const emailNotice = app.querySelector('[data-receipt-email-notice]');
            if (emailNotice) {
                emailNotice.textContent = '';
                emailNotice.classList.remove('is-error');
            }
            showModal('[data-receipt-modal]');
            const notice = app.querySelector('[data-save-notice]');
            if (notice) {
                notice.textContent = later
                    ? `Saved #${result.transaction?.id || ''} · unpaid · collect ${money(s.total)} on release`
                    : s.hasLaundry
                      ? `Saved #${result.transaction?.id || ''} · paid · change ${money(s.change)}`
                      : `Purchase saved · change ${money(s.change)}`;
                notice.classList.remove('is-error');
            }
            resetTransactionForm();
            if (typeof loadStaffBootstrap === 'function') loadStaffBootstrap();
            if (confirmBtn) confirmBtn.disabled = false;
        } catch (err) {
            showPayError(err.message || 'Could not save transaction.');
            // A failed attempt saved nothing, so the retry gets a fresh payment token.
            app._payToken = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random();
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
                    ? 'Pick a machine or add snacks before saving.'
                    : 'Add garments or snacks before saving.';
                notice.classList.add('is-error');
            }
            return;
        }
        if (!s.selfService && s.garments > 0 && !(s.service.amount > 0)) {
            if (notice) {
                notice.textContent = 'Pick a service type for the garments, or remove them to sell snacks and drinks only.';
                notice.classList.add('is-error');
            }
            return;
        }
        if (s.selfService && !s.machines.length) {
            if (notice) {
                notice.textContent = 'Pick at least one washer or dryer.';
                notice.classList.add('is-error');
            }
            return;
        }
        if (notice) {
            notice.textContent = '';
            notice.classList.remove('is-error');
        }
        openPaymentModal(s.payLater ? 'later' : 'new');
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

        const pick = t.closest('[data-pick-machine]');
        if (pick) {
            e.preventDefault();
            const id = Number(pick.dataset.pickMachine);
            if (picked().has(id)) {
                picked().delete(id);
            } else {
                const m = machines().find((x) => x.id === id);
                picked().set(id, m?.type === 'dryer' ? dryMinutesFor(m.size)[0] : WASH_MINUTES);
            }
            renderMachinePicker();
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
        if (t.closest('[data-send-receipt-email]')) {
            e.preventDefault();
            const emailNotice = app.querySelector('[data-receipt-email-notice]');
            if (!app._receiptCustomerEmail || !app._receiptTransactionId) {
                if (emailNotice) emailNotice.textContent = 'No customer email address is available.';
                return true;
            }
            const sendBtn = t.closest('[data-send-receipt-email]');
            sendBtn.disabled = true;
            if (emailNotice) {
                emailNotice.textContent = 'Sending receipt…';
                emailNotice.classList.remove('is-error');
            }
            api(`/ajax/staff/transactions/${app._receiptTransactionId}/receipt-email`, { method: 'POST', body: '{}' })
                .then((r) => {
                    const words = {
                        sent: `Receipt emailed to ${app._receiptCustomerEmail}.`,
                        logged: 'Email delivery is not configured, so the receipt was logged but not sent.',
                    };
                    if (emailNotice) emailNotice.textContent = words[r.result] || 'Receipt email processed.';
                })
                .catch((err) => {
                    if (emailNotice) {
                        emailNotice.textContent = err.message;
                        emailNotice.classList.add('is-error');
                    }
                })
                .finally(() => {
                    sendBtn.disabled = false;
                });
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
        if (t.matches('[data-pick-minutes]')) {
            picked().set(Number(t.dataset.pickMinutes), Number(t.value));
            update();
            return true;
        }
        if (
            t.matches('[data-load-kg]') ||
            t.matches('[data-consumable]') ||
            t.matches('[data-consumable-qty]') ||
            t.matches('[data-service-price]') ||
            t.matches('[data-pay-later]')
        ) {
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
        collectOnRelease,
    };
}
