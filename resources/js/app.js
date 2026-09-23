/**
 * SSK Laba Dami — staff dashboard
 * Flow: Transaction → Payment (cash + change) → Receipt
 */
(function () {
    function boot() {
        const app = document.querySelector('[data-staff-app]');
        if (!app) return;
        if (app.dataset.sskBound === '1') return;
        app.dataset.sskBound = '1';

        const money = (n) =>
            new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(n) || 0);

        const csrf = () =>
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const api = async (url, options = {}) => {
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
            return body;
        };

        const activeWorkflow = () => {
            const open = app.querySelector('[data-workflow]:not([hidden])');
            return open || app.querySelector('[data-workflow="drop_off"]') || app.querySelector('[data-workflow]');
        };

        const customerFullName = () => {
            const first = (app.querySelector('[data-customer-first]')?.value || '').trim();
            const middle = (app.querySelector('[data-customer-middle]')?.value || '').trim();
            const last = (app.querySelector('[data-customer-last]')?.value || '').trim();
            return [first, middle, last].filter(Boolean).join(' ');
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
                total += Number(el.value || el.textContent || 0);
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
                        line: Number(item.dataset.price || 0) * qty,
                    });
                }
            });
            return items;
        };

        const isSelfService = () =>
            app.querySelector('[data-service].is-selected')?.dataset?.service === 'self_service';

        /** Consumable: unit price × quantity in the active workflow */
        const getConsumable = (workflow) => {
            if (!workflow) return { amount: 0, label: 'None', qty: 0, unit: 0 };
            const select = workflow.querySelector('[data-consumable]');
            const qtyInput = workflow.querySelector('[data-consumable-qty]');
            const unit = Number(select?.value || 0);
            const qty = Number(qtyInput?.value || 0);
            const optText = select?.selectedOptions?.[0]?.textContent?.trim() || 'None';
            if (unit <= 0 || qty <= 0) {
                return { amount: 0, label: 'None', qty: 0, unit: 0 };
            }
            const name = optText.split('·')[0].trim();
            return {
                amount: unit * qty,
                label: `${name} × ${qty}`,
                qty,
                unit,
            };
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
            if (kg <= 4) return '30';
            if (kg <= 7) return '45';
            if (kg <= 10) return '60';
            if (kg <= 13) return '75';
            return '90';
        };

        const getServiceCharge = (workflow, selfService) => {
            if (!workflow) {
                return { amount: 0, label: '—', kg: 0, billableKg: 0, duration: null, durationText: null, rate: 0, pricing: 'flat' };
            }
            const priceSelect = workflow.querySelector('[data-service-price]');
            const pricing = priceSelect?.dataset?.pricing || 'flat';
            const label = priceSelect?.selectedOptions?.[0]?.textContent?.trim() || '—';
            const kg = getLoadKg(workflow);
            const durationSelect = workflow.querySelector('[data-cycle-duration]');
            const duration = durationSelect?.value || null;
            const durationText = durationSelect?.selectedOptions?.[0]?.textContent?.trim() || null;

            if (selfService || pricing === 'per_kg') {
                const rate = Number(priceSelect?.selectedOptions?.[0]?.dataset?.rate || priceSelect?.value || 0);
                const billableKg = kg > 0 ? Math.max(kg, MIN_BILLABLE_KG) : 0;
                return {
                    amount: billableKg > 0 ? billableKg * rate : 0,
                    label,
                    kg,
                    billableKg,
                    duration,
                    durationText,
                    rate,
                    pricing: 'per_kg',
                };
            }

            return {
                amount: Number(priceSelect?.value || 0),
                label,
                kg,
                billableKg: kg,
                duration: null,
                durationText: null,
                rate: 0,
                pricing: 'flat',
            };
        };

        const update = () => {
            try {
                const workflow = activeWorkflow();
                const garments = getGarmentTotal();
                const addOns = getAddOns();
                const selfService = isSelfService();
                const loadKg = getLoadKg(workflow);
                // Drop-off: garments; Self-service: kg required for laundry charge
                const hasLaundry = selfService ? loadKg > 0 : garments > 0;
                const hasAddOns = addOns.length > 0;
                const consumable = getConsumable(workflow);
                const service = getServiceCharge(workflow, selfService);

                if (selfService && workflow) {
                    const dur = workflow.querySelector('[data-cycle-duration]');
                    const hint = workflow.querySelector('[data-duration-hint]');
                    const suggested = suggestDuration(loadKg);
                    if (dur && suggested && !dur.dataset.userSet) {
                        dur.value = suggested;
                    }
                    if (hint) {
                        if (loadKg > 0 && suggested) {
                            hint.textContent = `Suggested: ${suggested} min for ${loadKg} kg (you can change it).`;
                        } else {
                            hint.textContent = 'Suggested from weight when you enter kg.';
                        }
                    }
                    const preview = workflow.querySelector('[data-kg-preview]');
                    if (preview) {
                        if (service.amount > 0) {
                            preview.hidden = false;
                            const minNote = loadKg > 0 && loadKg < MIN_BILLABLE_KG ? ` · min ${MIN_BILLABLE_KG} kg` : '';
                            preview.innerHTML = `Load charge: <strong>${money(service.amount)}</strong> <small>(${service.billableKg} kg × ${money(service.rate)}/kg${minNote})</small>`;
                        } else {
                            preview.hidden = true;
                        }
                    }
                }

                const serviceAmount = hasLaundry ? service.amount : 0;
                const serviceLabel = service.label;
                const consumableAmount = hasLaundry ? consumable.amount : 0;
                const addOnTotal = addOns.reduce((s, i) => s + i.line, 0);
                const total = serviceAmount + consumableAmount + addOnTotal;
                const anything = hasLaundry || hasAddOns;

                const titleEl = app.querySelector('[data-summary-title]');
                const emptyEl = app.querySelector('[data-summary-empty]');
                const serviceBlock = app.querySelector('[data-summary-service-block]');
                const itemsBlock = app.querySelector('[data-summary-items-block]');
                const itemsContainer = app.querySelector('[data-summary-items]');
                const totalBlock = app.querySelector('[data-summary-total-block]');
                const saveBtn = app.querySelector('[data-save]');

                if (titleEl) {
                    titleEl.textContent = !anything
                        ? 'Summary'
                        : hasLaundry
                          ? 'Service Summary'
                          : 'Purchase Summary';
                }
                if (emptyEl) emptyEl.hidden = anything;
                if (totalBlock) totalBlock.hidden = !anything;
                if (saveBtn) {
                    saveBtn.disabled = !anything;
                    saveBtn.textContent = hasLaundry ? 'Save Transaction' : 'Save Purchase';
                }

                if (serviceBlock) {
                    serviceBlock.hidden = !hasLaundry;
                    if (hasLaundry) {
                        const tag = selfService ? '—' : app.querySelector('[data-basket-tag]')?.value || '—';
                        const summaryTag = app.querySelector('[data-summary-tag]');
                        if (summaryTag) summaryTag.textContent = tag;
                        const summaryService = app.querySelector('[data-summary-service]');
                        if (summaryService) summaryService.textContent = selfService ? 'Self Service' : 'Drop Off';
                        app.querySelectorAll('[data-summary-garments]').forEach((el) => {
                            el.textContent = selfService ? '—' : String(garments);
                        });
                        const rowGarments = app.querySelector('[data-row-garments]');
                        if (rowGarments) rowGarments.hidden = selfService;
                        const rowBasket = app.querySelector('[data-row-basket]');
                        if (rowBasket) rowBasket.hidden = selfService;

                        const rowKg = app.querySelector('[data-row-kg]');
                        const summaryKg = app.querySelector('[data-summary-kg]');
                        if (rowKg) rowKg.hidden = !(service.kg > 0);
                        if (summaryKg) {
                            summaryKg.textContent =
                                service.pricing === 'per_kg' && service.billableKg > 0
                                    ? `${service.kg} kg${service.kg < MIN_BILLABLE_KG ? ` (billed ${service.billableKg} kg)` : ''} · ${money(service.rate)}/kg`
                                    : `${service.kg} kg`;
                        }

                        const rowDuration = app.querySelector('[data-row-duration]');
                        const summaryDuration = app.querySelector('[data-summary-duration]');
                        if (rowDuration) rowDuration.hidden = !selfService || !service.duration;
                        if (summaryDuration) {
                            summaryDuration.textContent = service.duration ? `${service.duration} min` : '—';
                        }

                        const rowServiceType = app.querySelector('[data-row-service-type]');
                        const summaryServiceType = app.querySelector('[data-summary-service-type]');
                        if (rowServiceType) rowServiceType.hidden = false;
                        if (summaryServiceType) summaryServiceType.textContent = serviceLabel;
                        const rowConsumable = app.querySelector('[data-row-consumable]');
                        const summaryConsumable = app.querySelector('[data-summary-consumable]');
                        if (rowConsumable) rowConsumable.hidden = consumableAmount <= 0;
                        if (summaryConsumable) summaryConsumable.textContent = consumable.label;
                    }
                }

                if (itemsBlock && itemsContainer) {
                    if (hasAddOns) {
                        itemsBlock.hidden = false;
                        itemsContainer.innerHTML = addOns
                            .map(
                                (i) =>
                                    `<div class="summary-item-row"><dt>${i.name} × ${i.qty}</dt><dd>${money(i.line)}</dd></div>`
                            )
                            .join('');
                    } else {
                        itemsBlock.hidden = true;
                        itemsContainer.innerHTML = '';
                    }
                }

                const totalEl = app.querySelector('[data-total]');
                if (totalEl) totalEl.textContent = money(total);

                app._lastSummary = {
                    hasLaundry,
                    hasAddOns,
                    selfService,
                    garments,
                    serviceLabel,
                    consumableLabel: consumable.label,
                    consumableAmount,
                    serviceAmount,
                    addOns,
                    total,
                    tag: selfService ? '—' : app.querySelector('[data-basket-tag]')?.value || '—',
                    serviceMode: selfService ? 'Self Service' : 'Drop Off',
                    customer: customerFullName(),
                    contact: (app.querySelector('[data-customer-contact]')?.value || '').trim(),
                    notes: (app.querySelector('.notes-card textarea')?.value || '').trim(),
                    title: hasLaundry ? 'Service Summary' : hasAddOns ? 'Purchase Summary' : 'Summary',
                    kg: service.kg,
                    billableKg: service.billableKg,
                    ratePerKg: service.rate,
                    pricing: service.pricing,
                    duration: service.duration,
                    durationText: service.durationText,
                    cashReceived: 0,
                    change: 0,
                };
            } catch (err) {
                console.error('SSK update error:', err);
            }
        };

        let pendingBasket = null;

        const showModal = (sel) => {
            const el = document.querySelector(sel);
            if (el) {
                el.hidden = false;
                el.style.display = '';
            }
        };
        const hideModal = (sel) => {
            const el = document.querySelector(sel);
            if (el) {
                el.hidden = true;
                el.style.display = 'none';
            }
        };

        const showModalView = (view) => {
            app.querySelectorAll('[data-modal-view]').forEach((panel) => {
                panel.hidden = panel.dataset.modalView !== view;
            });
        };

        const syncAppearanceControls = () => {
            const root = document.documentElement;
            const theme = root.getAttribute('data-theme') || 'light';
            const textSize = root.getAttribute('data-text-size') || 'normal';
            app.querySelectorAll('[data-theme-option]').forEach((btn) => {
                btn.classList.toggle('is-selected', btn.dataset.themeOption === theme);
            });
            app.querySelectorAll('[data-text-size-option]').forEach((btn) => {
                btn.classList.toggle('is-selected', btn.dataset.textSizeOption === textSize);
            });
        };

        const openNotice = (title, body) => {
            const t = document.querySelector('[data-notice-title]');
            const b = document.querySelector('[data-notice-body]');
            if (t) t.textContent = title || 'Notice';
            if (b) b.textContent = body || '';
            showModal('[data-notice-modal]');
        };

        const openPaymentModal = () => {
            const s = app._lastSummary;
            if (!s || s.total <= 0) return;

            const summaryEl = document.querySelector('[data-payment-summary]');
            if (summaryEl) {
                const bits = [];
                if (s.customer) bits.push(`<div><strong>Customer</strong><span>${s.customer}</span></div>`);
                if (s.hasLaundry) {
                    if (!s.selfService) bits.push(`<div><strong>Basket</strong><span>${s.tag}</span></div>`);
                    bits.push(`<div><strong>Service</strong><span>${s.serviceMode}</span></div>`);
                    bits.push(`<div><strong>Service type</strong><span>${s.serviceLabel}</span></div>`);
                    if (s.consumableAmount > 0) {
                        bits.push(`<div><strong>Consumable</strong><span>${s.consumableLabel}</span></div>`);
                    }
                }
                s.addOns.forEach((i) => {
                    bits.push(`<div><strong>${i.name} × ${i.qty}</strong><span>${money(i.line)}</span></div>`);
                });
                summaryEl.innerHTML = bits.join('');
            }

            const due = document.querySelector('[data-payment-due]');
            if (due) due.value = money(s.total);

            const cash = document.querySelector('[data-payment-cash]');
            if (cash) cash.value = '';

            const changeRow = document.querySelector('[data-payment-change-row]');
            if (changeRow) changeRow.hidden = true;

            const err = document.querySelector('[data-payment-error]');
            if (err) {
                err.hidden = true;
                err.textContent = '';
            }

            const confirm = document.querySelector('[data-confirm-payment]');
            if (confirm) confirm.disabled = true;

            showModal('[data-payment-modal]');
            setTimeout(() => cash?.focus(), 50);
        };

        const recalcPaymentChange = () => {
            const s = app._lastSummary;
            const cashInput = document.querySelector('[data-payment-cash]');
            const changeRow = document.querySelector('[data-payment-change-row]');
            const changeEl = document.querySelector('[data-payment-change]');
            const confirm = document.querySelector('[data-confirm-payment]');
            const err = document.querySelector('[data-payment-error]');
            if (!s || !cashInput) return;

            const cash = Number(cashInput.value);
            const due = Number(s.total);

            if (!cashInput.value || Number.isNaN(cash) || cash < 0) {
                if (changeRow) changeRow.hidden = true;
                if (confirm) confirm.disabled = true;
                if (err) err.hidden = true;
                return;
            }

            if (cash + 1e-9 < due) {
                if (changeRow) changeRow.hidden = true;
                if (confirm) confirm.disabled = true;
                if (err) {
                    err.hidden = false;
                    err.textContent = `Cash is short by ${money(due - cash)}. Enter at least the amount due.`;
                }
                return;
            }

            const change = cash - due;
            if (changeRow) changeRow.hidden = false;
            if (changeEl) changeEl.textContent = money(change);
            if (confirm) confirm.disabled = false;
            if (err) err.hidden = true;
        };

        const buildReceiptHtml = (s) => {
            const lines = [];
            lines.push(`<div class="receipt">`);
            lines.push(`<h2>SSK Laba Dami</h2>`);
            lines.push(`<p class="receipt-sub">Laundry Hub · ${s.title}</p>`);
            lines.push(`<p class="receipt-meta">${new Date().toLocaleString()}</p>`);
            if (s.customer) lines.push(`<p><strong>Customer:</strong> ${s.customer}</p>`);
            if (s.contact) lines.push(`<p><strong>Contact:</strong> ${s.contact}</p>`);
            lines.push(`<hr>`);
            if (s.hasLaundry) {
                if (!s.selfService) lines.push(`<p><strong>Basket:</strong> ${s.tag}</p>`);
                lines.push(`<p><strong>Service:</strong> ${s.serviceMode}</p>`);
                if (!s.selfService) lines.push(`<p><strong>Garments:</strong> ${s.garments} pcs</p>`);
                if (s.kg > 0) {
                    lines.push(
                        `<p><strong>Load weight:</strong> ${s.kg} kg${
                            s.pricing === 'per_kg' ? ` · billed ${s.billableKg} kg × ${money(s.ratePerKg)}/kg` : ''
                        }</p>`
                    );
                }
                if (s.duration) lines.push(`<p><strong>Cycle duration:</strong> ${s.duration} min</p>`);
                lines.push(`<p><strong>Service type:</strong> ${s.serviceLabel}</p>`);
                if (s.serviceAmount > 0) lines.push(`<p><strong>Load charge:</strong> ${money(s.serviceAmount)}</p>`);
                if (s.consumableAmount > 0) {
                    lines.push(`<p><strong>Consumable:</strong> ${s.consumableLabel} — ${money(s.consumableAmount)}</p>`);
                }
            }
            if (s.hasAddOns) {
                lines.push(`<p style="margin-top:10px"><strong>Items</strong></p>`);
                s.addOns.forEach((i) => lines.push(`<p>${i.name} × ${i.qty} — ${money(i.line)}</p>`));
            }
            lines.push(`<hr>`);
            lines.push(`<p><strong>Total:</strong> ${money(s.total)}</p>`);
            lines.push(`<p><strong>Cash:</strong> ${money(s.cashReceived)}</p>`);
            lines.push(`<p class="receipt-total"><strong>Change:</strong> ${money(s.change)}</p>`);
            if (s.notes) lines.push(`<p class="receipt-notes"><em>Notes: ${s.notes}</em></p>`);
            lines.push(`<p class="receipt-footer">Thank you!</p>`);
            lines.push(`</div>`);
            return lines.join('');
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
            const machineId = Number(workflow?.querySelector('[data-machine]')?.selectedOptions?.[0]?.dataset?.machineId || 0) || null;

            return {
                first_name: (app.querySelector('[data-customer-first]')?.value || '').trim(),
                middle_name: (app.querySelector('[data-customer-middle]')?.value || '').trim(),
                last_name: (app.querySelector('[data-customer-last]')?.value || '').trim(),
                contact_number: (app.querySelector('[data-customer-contact]')?.value || '').trim(),
                contact_email: (app.querySelector('[data-customer-email]')?.value || '').trim(),
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
            };
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
                // Refresh lists from server
                loadStaffBootstrap();
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
            const first = (app.querySelector('[data-customer-first]')?.value || '').trim();
            const last = (app.querySelector('[data-customer-last]')?.value || '').trim();
            const s = app._lastSummary;

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

            if (s.hasLaundry && (!first || !last)) {
                if (notice) {
                    notice.textContent = 'Enter first name and last name before saving a laundry transaction.';
                    notice.classList.add('is-error');
                }
                return;
            }

            if (notice) {
                notice.textContent = '';
                notice.classList.remove('is-error');
            }

            // Systematic flow: tally → payment modal (not receipt yet)
            openPaymentModal();
        };

        const addCustomGarment = () => {
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
                return;
            }

            const grid = app.querySelector('[data-garment-grid]');
            const addBtn = app.querySelector('[data-add-garment]');
            if (!grid || !addBtn) return;

            // Avoid duplicate names
            const existing = [...grid.querySelectorAll('.garment-counter span')].map((s) =>
                s.textContent.trim().toLowerCase()
            );
            if (existing.includes(name.toLowerCase())) {
                if (err) {
                    err.hidden = false;
                    err.textContent = `"${name}" is already in the list. Use +/- on that card.`;
                }
                return;
            }

            const card = document.createElement('div');
            card.className = 'garment-counter';
            card.innerHTML = `<span>${name}</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>${qty}</output><button data-garment-increase type="button">+</button></div>`;
            grid.insertBefore(card, addBtn);

            if (nameInput) nameInput.value = '';
            if (qtyInput) qtyInput.value = '0';
            if (err) err.hidden = true;
            hideModal('[data-garment-modal]');
            update();
        };

        // ——— Event delegation ———
        app.addEventListener('click', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;

            // Module navigation
            const screenBtn = t.closest('[data-screen]');
            if (screenBtn && app.contains(screenBtn)) {
                e.preventDefault();
                const screen = screenBtn.dataset.screen;
                app.querySelectorAll('[data-screen]').forEach((item) => {
                    item.classList.toggle('is-active', item === screenBtn);
                });
                app.querySelectorAll('[data-panel]').forEach((panel) => {
                    panel.classList.toggle('is-visible', panel.dataset.panel === screen);
                });
                app.classList.remove('sidebar-open');
                return;
            }

            if (t.closest('[data-sidebar-toggle]')) {
                e.preventDefault();
                app.classList.toggle('sidebar-open');
                return;
            }

            // Attendance
            if (t.closest('[data-clock-in]')) {
                e.preventDefault();
                api('/api/staff/attendance/clock-in', { method: 'POST', body: '{}' })
                    .then((r) => {
                        openNotice('Clock in', r.message || 'Clocked in');
                        loadStaffBootstrap();
                    })
                    .catch((err) => openNotice('Clock in', err.message));
                return;
            }
            if (t.closest('[data-clock-out]')) {
                e.preventDefault();
                api('/api/staff/attendance/clock-out', { method: 'POST', body: '{}' })
                    .then((r) => {
                        openNotice('Clock out', r.message || 'Clocked out');
                        loadStaffBootstrap();
                    })
                    .catch((err) => openNotice('Clock out', err.message));
                return;
            }

            if (t.closest('[data-open-restock]')) {
                e.preventDefault();
                showModal('[data-restock-modal]');
                return;
            }
            if (t.closest('[data-close-restock]')) {
                e.preventDefault();
                hideModal('[data-restock-modal]');
                return;
            }
            if (t.closest('[data-confirm-restock]')) {
                e.preventDefault();
                const btn = t.closest('[data-confirm-restock]');
                if (btn.disabled) return; // guard against double-click / duplicate dispatch double-submitting the same restock
                btn.disabled = true;
                const itemId = document.querySelector('[data-restock-item]')?.value;
                const qty = Number(document.querySelector('[data-restock-qty]')?.value || 0);
                const supplier = document.querySelector('[data-restock-supplier]')?.value || '';
                const err = document.querySelector('[data-restock-error]');
                api('/api/staff/inventory/restock', {
                    method: 'POST',
                    body: JSON.stringify({ inventory_item_id: Number(itemId), quantity_received: qty, supplier }),
                })
                    .then((r) => {
                        hideModal('[data-restock-modal]');
                        openNotice('Restock', r.message || 'Restocked');
                        loadStaffBootstrap();
                    })
                    .catch((ex) => {
                        if (err) {
                            err.hidden = false;
                            err.textContent = ex.message;
                        }
                    })
                    .finally(() => { btn.disabled = false; });
                return;
            }
            if (t.closest('[data-open-archive]')) {
                e.preventDefault();
                showModal('[data-archive-modal]');
                return;
            }
            if (t.closest('[data-close-archive]')) {
                e.preventDefault();
                hideModal('[data-archive-modal]');
                return;
            }
            if (t.closest('[data-confirm-archive]')) {
                e.preventDefault();
                const itemId = document.querySelector('[data-archive-item]')?.value;
                const reason = document.querySelector('[data-archive-reason]')?.value;
                const qty = Number(document.querySelector('[data-archive-qty]')?.value || 0);
                const err = document.querySelector('[data-archive-error]');
                api(`/api/staff/inventory/${itemId}/archive`, {
                    method: 'POST',
                    body: JSON.stringify({ reason, quantity: qty }),
                })
                    .then((r) => {
                        hideModal('[data-archive-modal]');
                        openNotice('Archive', r.message || 'Archived');
                        loadStaffBootstrap();
                    })
                    .catch((ex) => {
                        if (err) {
                            err.hidden = false;
                            err.textContent = ex.message;
                        }
                    });
                return;
            }

            if (t.closest('[data-open-add-machine]')) {
                e.preventDefault();
                showModal('[data-add-machine-modal]');
                return;
            }
            if (t.closest('[data-close-add-machine]')) {
                e.preventDefault();
                hideModal('[data-add-machine-modal]');
                return;
            }
            if (t.closest('[data-confirm-add-machine]')) {
                e.preventDefault();
                const btn = t.closest('[data-confirm-add-machine]');
                if (btn.disabled) return;
                btn.disabled = true;
                const name = document.querySelector('[data-machine-name]')?.value || '';
                const type = document.querySelector('[data-machine-type]')?.value || 'washer';
                const err = document.querySelector('[data-add-machine-error]');
                api('/api/staff/machines', {
                    method: 'POST',
                    body: JSON.stringify({ name, type }),
                })
                    .then((r) => {
                        hideModal('[data-add-machine-modal]');
                        openNotice('Machine', r.message || 'Machine added');
                        loadStaffBootstrap();
                    })
                    .catch((ex) => {
                        if (err) {
                            err.hidden = false;
                            err.textContent = ex.message;
                        }
                    })
                    .finally(() => { btn.disabled = false; });
                return;
            }

            const statusBtn = t.closest('[data-set-status]');
            if (statusBtn) {
                e.preventDefault();
                const id = statusBtn.dataset.setStatus;
                const next = statusBtn.dataset.nextStatus;
                api(`/api/staff/transactions/${id}/status`, {
                    method: 'PATCH',
                    body: JSON.stringify({ status: next }),
                })
                    .then(() => loadStaffBootstrap())
                    .catch((err) => openNotice('Status', err.message));
                return;
            }

            const emailBtn = t.closest('[data-notify-email]');
            if (emailBtn) {
                e.preventDefault();
                if (emailBtn.disabled) return;
                const id = emailBtn.dataset.notifyEmail;
                emailBtn.disabled = true;
                api(`/api/staff/transactions/${id}/notify-email`, { method: 'POST' })
                    .then((r) => { openNotice('Email', r.message || 'Email sent'); loadStaffBootstrap(); })
                    .catch((err) => { openNotice('Email', err.message); emailBtn.disabled = false; });
                return;
            }

            const smsBtn = t.closest('[data-notify-sms]');
            if (smsBtn) {
                e.preventDefault();
                if (smsBtn.disabled) return;
                const id = smsBtn.dataset.notifySms;
                smsBtn.disabled = true;
                api(`/api/staff/transactions/${id}/notify-sms`, { method: 'POST' })
                    .then((r) => { openNotice('SMS', r.message || 'SMS sent'); loadStaffBootstrap(); })
                    .catch((err) => { openNotice('SMS', err.message); smsBtn.disabled = false; });
                return;
            }

            const machBtn = t.closest('[data-machine-status]');
            if (machBtn) {
                e.preventDefault();
                const id = machBtn.dataset.machineStatus;
                const st = machBtn.dataset.setMachine;
                api(`/api/staff/machines/${id}/status`, {
                    method: 'PATCH',
                    body: JSON.stringify({ status: st }),
                })
                    .then(() => loadStaffBootstrap())
                    .catch((err) => openNotice('Machine', err.message));
                return;
            }

            // Secondary action buttons → notice modal
            const noticeBtn = t.closest('[data-action-notice]');
            if (noticeBtn) {
                e.preventDefault();
                openNotice(noticeBtn.dataset.noticeTitle, noticeBtn.dataset.noticeBody);
                return;
            }

            // Service mode
            const serviceBtn = t.closest('[data-service]');
            if (serviceBtn && app.contains(serviceBtn)) {
                e.preventDefault();
                const selfService = serviceBtn.dataset.service === 'self_service';
                app.querySelectorAll('[data-service]').forEach((item) => {
                    item.classList.toggle('is-selected', item === serviceBtn);
                });
                const dropOff = app.querySelector('[data-workflow="drop_off"]');
                const selfWf = app.querySelector('[data-workflow="self_service"]');
                if (dropOff) dropOff.hidden = selfService;
                if (selfWf) selfWf.hidden = !selfService;
                update();
                return;
            }

            // Snacks +/-
            const addInc = t.closest('[data-increase]');
            const addDec = t.closest('[data-decrease]');
            if (addInc || addDec) {
                e.preventDefault();
                const btn = addInc || addDec;
                const output = btn.parentElement?.querySelector('output');
                const current = Number(output?.value || output?.textContent || 0);
                setOutputValue(output, current + (addInc ? 1 : -1));
                update();
                return;
            }

            // Garments +/-
            const gInc = t.closest('[data-garment-increase]');
            const gDec = t.closest('[data-garment-decrease]');
            if (gInc || gDec) {
                e.preventDefault();
                const btn = gInc || gDec;
                const output = btn.parentElement?.querySelector('output');
                const current = Number(output?.value || output?.textContent || 0);
                setOutputValue(output, current + (gInc ? 1 : -1));
                update();
                return;
            }

            // Consumable quantity +/-
            const cInc = t.closest('[data-consumable-increase]');
            const cDec = t.closest('[data-consumable-decrease]');
            if (cInc || cDec) {
                e.preventDefault();
                const wrap = (cInc || cDec).closest('.qty-field') || (cInc || cDec).parentElement;
                const input = wrap?.querySelector('[data-consumable-qty]');
                if (input) {
                    const next = Math.max(0, Number(input.value || 0) + (cInc ? 1 : -1));
                    input.value = String(next);
                    update();
                }
                return;
            }

            if (t.closest('[data-clear-items]')) {
                e.preventDefault();
                app.querySelectorAll('.quantity-control output').forEach((o) => setOutputValue(o, 0));
                update();
                return;
            }

            // Add garment type
            if (t.closest('[data-add-garment]')) {
                e.preventDefault();
                const err = document.querySelector('[data-garment-modal-error]');
                if (err) err.hidden = true;
                const nameInput = document.querySelector('[data-new-garment-name]');
                const qtyInput = document.querySelector('[data-new-garment-qty]');
                if (nameInput) nameInput.value = '';
                if (qtyInput) qtyInput.value = '0';
                showModal('[data-garment-modal]');
                setTimeout(() => nameInput?.focus(), 50);
                return;
            }

            if (t.closest('[data-close-garment-modal]')) {
                e.preventDefault();
                hideModal('[data-garment-modal]');
                return;
            }

            if (t.closest('[data-confirm-garment]')) {
                e.preventDefault();
                addCustomGarment();
                return;
            }

            // Basket reassign
            if (t.closest('[data-reassign-basket]')) {
                e.preventDefault();
                const picker = app.querySelector('[data-basket-picker]');
                if (!picker) return;
                pendingBasket = null;
                picker.hidden = false;
                picker.querySelectorAll('[data-basket-option]').forEach((btn) => btn.classList.remove('is-selected'));
                const confirm = app.querySelector('[data-basket-confirm]');
                if (confirm) confirm.disabled = true;
                return;
            }

            const basketOpt = t.closest('[data-basket-option]');
            if (basketOpt) {
                e.preventDefault();
                pendingBasket = basketOpt.dataset.basketOption;
                app.querySelectorAll('[data-basket-option]').forEach((b) => {
                    b.classList.toggle('is-selected', b === basketOpt);
                });
                const confirm = app.querySelector('[data-basket-confirm]');
                if (confirm) confirm.disabled = false;
                return;
            }

            if (t.closest('[data-basket-cancel]')) {
                e.preventDefault();
                const picker = app.querySelector('[data-basket-picker]');
                if (picker) picker.hidden = true;
                pendingBasket = null;
                return;
            }

            if (t.closest('[data-basket-confirm]')) {
                e.preventDefault();
                if (!pendingBasket) return;
                const tagInput = app.querySelector('[data-basket-tag]');
                if (tagInput) tagInput.value = pendingBasket;
                const picker = app.querySelector('[data-basket-picker]');
                if (picker) picker.hidden = true;
                pendingBasket = null;
                update();
                return;
            }

            // Save → payment
            if (t.closest('[data-save]')) {
                e.preventDefault();
                handleSaveClick();
                return;
            }

            // Payment
            if (t.closest('[data-close-payment]')) {
                e.preventDefault();
                hideModal('[data-payment-modal]');
                return;
            }

            if (t.closest('[data-confirm-payment]')) {
                e.preventDefault();
                confirmPayment();
                return;
            }

            // Receipt
            if (t.closest('[data-close-receipt]')) {
                e.preventDefault();
                hideModal('[data-receipt-modal]');
                return;
            }

            if (t.closest('[data-print-receipt]')) {
                e.preventDefault();
                const body = document.querySelector('[data-receipt-body]');
                if (!body) return;
                const win = window.open('', '_blank', 'width=420,height=640');
                if (!win) return;
                win.document.write(`<!DOCTYPE html><html><head><title>Receipt</title>
                    <style>
                        body{font-family:system-ui,sans-serif;padding:24px;color:#143529}
                        h2{margin:0 0 4px;font-size:20px}
                        .receipt-sub,.receipt-meta{color:#718278;font-size:13px;margin:0 0 4px}
                        hr{border:none;border-top:1px dashed #b6c1b8;margin:12px 0}
                        .receipt-total{font-size:18px}
                        .receipt-footer{margin-top:16px;text-align:center;color:#718278}
                    </style></head><body>${body.innerHTML}</body></html>`);
                win.document.close();
                win.focus();
                win.print();
                return;
            }

            // Notice modal
            if (t.closest('[data-close-notice]')) {
                e.preventDefault();
                hideModal('[data-notice-modal]');
                return;
            }

            // Profile
            if (t.closest('[data-profile]')) {
                e.preventDefault();
                showModalView('menu');
                const modal = app.querySelector('[data-modal]');
                if (modal) {
                    modal.hidden = false;
                    modal.style.display = '';
                }
                return;
            }

            if (t.closest('[data-close-modal]')) {
                e.preventDefault();
                const modal = app.querySelector('[data-modal]');
                if (modal) {
                    modal.hidden = true;
                    modal.style.display = 'none';
                }
                showModalView('menu');
                return;
            }

            const showView = t.closest('[data-show-view]');
            if (showView) {
                e.preventDefault();
                showModalView(showView.dataset.showView);
                if (showView.dataset.showView === 'display') syncAppearanceControls();
                return;
            }

            const themeOpt = t.closest('[data-theme-option]');
            if (themeOpt) {
                e.preventDefault();
                document.documentElement.setAttribute('data-theme', themeOpt.dataset.themeOption);
                try {
                    localStorage.setItem('ssk-theme', themeOpt.dataset.themeOption);
                } catch (err) {}
                syncAppearanceControls();
                return;
            }

            const textOpt = t.closest('[data-text-size-option]');
            if (textOpt) {
                e.preventDefault();
                const size = textOpt.dataset.textSizeOption;
                if (size === 'normal') document.documentElement.removeAttribute('data-text-size');
                else document.documentElement.setAttribute('data-text-size', size);
                try {
                    localStorage.setItem('ssk-text-size', size);
                } catch (err) {}
                syncAppearanceControls();
                return;
            }
        });

        // Payment cash typing
        document.addEventListener('input', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.matches('[data-payment-cash]')) recalcPaymentChange();
        });

        app.addEventListener('change', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.matches('[data-cycle-duration]')) {
                t.dataset.userSet = '1';
            }
            if (t.closest('[data-workflow]') || t.matches('[data-consumable]')) {
                if (t.matches('[data-consumable]') && Number(t.value) === 0) {
                    const wf = t.closest('[data-workflow]');
                    const qty = wf?.querySelector('[data-consumable-qty]');
                    if (qty) qty.value = '0';
                }
                update();
            }
        });

        app.addEventListener('input', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.matches('[data-customer-search]')) {
                const q = (t.value || '').toLowerCase().trim();
                app.querySelectorAll('[data-customer-row]').forEach((row) => {
                    const hay = (row.dataset.name || '') + ' ' + (row.dataset.contact || '');
                    row.style.display = !q || hay.includes(q) ? '' : 'none';
                });
                return;
            }
            if (t.matches('[data-load-kg]')) {
                // Allow re-suggestion when kg changes until user picks duration manually
                const wf = t.closest('[data-workflow]');
                const dur = wf?.querySelector('[data-cycle-duration]');
                if (dur) delete dur.dataset.userSet;
                update();
            }
        });


        const fmtTime = (iso) => {
            if (!iso) return '—';
            try {
                return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            } catch (e) {
                return '—';
            }
        };
        const fmtDate = (iso) => {
            if (!iso) return '—';
            try {
                const d = new Date(iso);
                const today = new Date();
                if (d.toDateString() === today.toDateString()) return 'Today';
                return d.toLocaleDateString();
            } catch (e) {
                return '—';
            }
        };

        const resetTransactionForm = () => {
            app.querySelectorAll('[data-customer-first], [data-customer-middle], [data-customer-last], [data-customer-contact], [data-customer-email]').forEach((el) => {
                el.value = '';
            });
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
            // Remove custom garment cards (keep first 3 defaults + add button)
            const grid = app.querySelector('[data-garment-grid]');
            if (grid) {
                grid.querySelectorAll('.garment-counter').forEach((card, idx) => {
                    if (idx >= 3) card.remove();
                });
            }
            update();
        };

        const renderAttendance = (data) => {
            const today = data.attendance_today;
            const recent = data.attendance_recent || [];
            const st = app.querySelector('[data-att-status]');
            const sub = app.querySelector('[data-att-status-sub]');
            const hours = app.querySelector('[data-att-hours]');
            const count = app.querySelector('[data-att-count]');
            if (count) count.textContent = String(recent.length);
            if (today && today.clock_in && !today.clock_out) {
                if (st) st.textContent = 'On duty';
                if (sub) sub.textContent = 'Clocked in ' + fmtTime(today.clock_in);
                if (hours) hours.textContent = '—';
            } else if (today && today.clock_out) {
                if (st) st.textContent = 'Complete';
                if (sub) sub.textContent = fmtTime(today.clock_in) + ' – ' + fmtTime(today.clock_out);
                try {
                    const h = (new Date(today.clock_out) - new Date(today.clock_in)) / 3600000;
                    if (hours) hours.textContent = h.toFixed(1) + ' h';
                } catch (e) {
                    if (hours) hours.textContent = '—';
                }
            } else {
                if (st) st.textContent = 'Off duty';
                if (sub) sub.textContent = 'Not clocked in';
                if (hours) hours.textContent = '—';
            }
            const rows = app.querySelector('[data-attendance-rows]');
            if (rows) {
                if (!recent.length) {
                    rows.innerHTML = '<div class="order-row"><span style="grid-column:1/-1">No attendance records yet.</span></div>';
                } else {
                    rows.innerHTML = recent
                        .map((r) => {
                            const done = !!r.clock_out;
                            return `<div class="order-row"><strong>${fmtDate(r.work_date || r.clock_in)}</strong><span>${fmtTime(r.clock_in)}</span><span>${fmtTime(r.clock_out)}</span><em class="status ${done ? 'ready' : 'processing'}">${done ? 'Complete' : 'On duty'}</em></div>`;
                        })
                        .join('');
                }
            }
        };

        const statusLabel = (st) => {
            const map = {
                pending: 'Pending',
                processing: 'Processing',
                ready_for_pickup: 'Ready for pickup',
                claimed: 'Claimed',
                cancelled: 'Cancelled',
            };
            return map[st] || st;
        };
        const statusClass = (st) => {
            if (st === 'processing') return 'processing';
            if (st === 'ready_for_pickup') return 'ready';
            if (st === 'pending') return 'pending';
            return 'ready';
        };

        const renderStaffLists = (data) => {
            // Queue
            const q = app.querySelector('[data-queue-rows]');
            if (q) {
                const list = data.active_laundry || [];
                q.innerHTML = list.length
                    ? list
                          .map((tx) => {
                              const basket = tx.basket_tag?.code || ('#' + tx.id);
                              const cust = tx.customer?.name || '—';
                              const svc = tx.service?.name || tx.transaction_type || '—';
                              const machine = tx.machine?.name ? `<br><small>${tx.machine.name}</small>` : '';

                              let actions = '';
                              if (tx.status === 'pending') {
                                  actions = `<button type="button" class="text-button" data-set-status="${tx.id}" data-next-status="processing">Mark Processing</button>`;
                              } else if (tx.status === 'processing') {
                                  actions = `<button type="button" class="text-button" data-set-status="${tx.id}" data-next-status="ready_for_pickup">Mark Ready for pickup</button>`;
                              } else if (tx.status === 'ready_for_pickup') {
                                  const emailed = !!tx.email_sent_at;
                                  const texted = !!tx.sms_sent_at;
                                  if (emailed && texted) {
                                      actions = `<button type="button" class="text-button" data-set-status="${tx.id}" data-next-status="claimed">Mark Claimed</button>`;
                                  } else {
                                      actions = `
                                          <button type="button" class="text-button" data-notify-email="${tx.id}" ${emailed ? 'disabled' : ''}>${emailed ? '✓ Emailed' : 'Send Email'}</button>
                                          <button type="button" class="text-button" data-notify-sms="${tx.id}" ${texted ? 'disabled' : ''}>${texted ? '✓ Texted' : 'Send SMS'}</button>
                                      `;
                                  }
                              }

                              return `<div class="order-row"><strong>${basket}</strong><span>${cust}</span><span>${svc}${machine}</span><span><em class="status ${statusClass(tx.status)}">${statusLabel(tx.status)}</em></span><span class="order-actions">${actions}</span></div>`;
                          })
                          .join('')
                    : '<div class="order-row"><span style="grid-column:1/-1">No active laundry orders.</span></div>';
            }

            // Customers
            const cr = app.querySelector('[data-customer-rows]');
            if (cr) {
                const list = data.customers || [];
                cr.innerHTML = list.length
                    ? list
                          .map(
                              (c) =>
                                  `<div class="order-row" data-customer-row data-name="${(c.name || '').toLowerCase()}" data-contact="${(c.contact_number || '').toLowerCase()}"><strong>${c.name}</strong><span>${c.contact_number || '—'}</span><span>${c.orders_count ?? 0}</span><span>${c.last_service ? fmtDate(c.last_service) : '—'}</span></div>`
                          )
                          .join('')
                    : '<div class="order-row"><span style="grid-column:1/-1">No customers yet.</span></div>';
            }

            // Inventory
            const ir = app.querySelector('[data-inventory-rows]');
            if (ir) {
                const list = data.inventory || [];
                ir.innerHTML = list.length
                    ? list
                          .map((i) => {
                              const low = i.quantity_on_hand <= i.low_stock_threshold;
                              return `<div class="order-row"><strong>${i.name}</strong><span>${i.category?.name || '—'}</span><span>${i.quantity_on_hand} ${i.unit}</span><em class="status ${low ? 'pending' : 'ready'}">${low ? 'Low stock' : 'In stock'}</em></div>`;
                          })
                          .join('')
                    : '<div class="order-row"><span style="grid-column:1/-1">No items.</span></div>';
            }
            const ar = app.querySelector('[data-archived-rows]');
            if (ar) {
                const list = data.archived_inventory || [];
                ar.innerHTML = list.length
                    ? list
                          .map((i) => {
                              const reason = (i.status || '').replace('archived_', '') || 'archived';
                              return `<div class="order-row"><strong>${i.name}</strong><span>${i.category?.name || '—'}</span><span>${reason}</span><span>${i.quantity_on_hand}</span></div>`;
                          })
                          .join('')
                    : '<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No archived items.</span></div>';
            }

            // Baskets
            const br = app.querySelector('[data-basket-rows]');
            if (br) {
                const list = data.baskets || [];
                br.innerHTML = list
                    .map((b) => {
                        const cls = b.status === 'available' ? 'ready' : 'processing';
                        return `<div class="order-row"><strong>${b.code}</strong><span><em class="status ${cls}">${b.status}</em></span><span>—</span><span></span></div>`;
                    })
                    .join('');
            }

            // Machines
            const mg = app.querySelector('[data-machine-grid]');
            if (mg) {
                const list = data.machines || [];
                mg.innerHTML = list
                    .map((m) => {
                        const avail = m.status === 'available';
                        return `<article class="staff-card" data-machine-id="${m.id}"><span>${m.name}</span><strong class="${avail ? 'available' : ''}">${m.status.replace(/_/g, ' ')}</strong><small>${m.type}</small>
                        <button type="button" data-machine-status="${m.id}" data-set-machine="${avail ? 'maintenance' : 'available'}">${avail ? 'Set maintenance' : 'Set available'}</button></article>`;
                    })
                    .join('');
            }

            // Restock / archive selects
            const fillSelect = (sel, items) => {
                if (!sel) return;
                sel.innerHTML = (items || [])
                    .map((i) => `<option value="${i.id}">${i.name} (${i.quantity_on_hand} ${i.unit})</option>`)
                    .join('');
            };
            fillSelect(document.querySelector('[data-restock-item]'), data.inventory);
            fillSelect(document.querySelector('[data-archive-item]'), data.inventory);

            renderAttendance(data);
        };

        const loadStaffBootstrap = () =>
            api('/api/staff/bootstrap')
                .then((data) => {
                    app._bootstrap = data;
                    (data.snacks || []).forEach((snack) => {
                        app.querySelectorAll('.add-on').forEach((row) => {
                            const name = row.querySelector('strong')?.textContent?.trim();
                            if (name && name === snack.name) {
                                row.dataset.itemId = String(snack.id);
                                row.dataset.price = String(snack.unit_price);
                                const small = row.querySelector('small');
                                if (small) {
                                    small.textContent = `₱${snack.unit_price} · ${snack.unit} · ${snack.quantity_on_hand} stock`;
                                }
                            }
                        });
                    });
                    app.querySelectorAll('[data-consumable]').forEach((select) => {
                        const current = select.value;
                        select.innerHTML = '<option value="0">None</option>';
                        (data.detergents || []).forEach((d) => {
                            const o = document.createElement('option');
                            o.value = String(d.unit_price);
                            o.dataset.itemId = String(d.id);
                            o.textContent = `${d.name} · ₱${d.unit_price} each`;
                            select.appendChild(o);
                        });
                        select.value = current;
                    });
                    app.querySelectorAll('[data-service-price][data-pricing="flat"]').forEach((select) => {
                        if (!(data.services || []).length) return;
                        select.innerHTML = '';
                        data.services.forEach((svc) => {
                            const o = document.createElement('option');
                            o.value = String(svc.base_price);
                            o.dataset.serviceId = String(svc.id);
                            o.textContent = `${svc.name} · ₱${svc.base_price}`;
                            select.appendChild(o);
                        });
                    });
                    const machineSelect = app.querySelector('[data-machine]');
                    if (machineSelect && (data.machines || []).length) {
                        machineSelect.innerHTML = '';
                        data.machines.forEach((m) => {
                            const o = document.createElement('option');
                            o.value = m.name;
                            o.dataset.machineId = String(m.id);
                            o.textContent = m.name;
                            machineSelect.appendChild(o);
                        });
                    }
                    const list = app.querySelector('[data-basket-list]');
                    if (list && (data.available_baskets || []).length) {
                        list.innerHTML = data.available_baskets
                            .map(
                                (b) =>
                                    `<li><button type="button" data-basket-option="${b.code}">${b.code} <small>${b.status}</small></button></li>`
                            )
                            .join('');
                    }
                    renderStaffLists(data);
                    update();
                })
                .catch((err) => console.warn('SSK bootstrap:', err.message));


        // Hide overlays on load
        document.querySelectorAll('.modal-backdrop[hidden]').forEach((el) => {
            el.style.display = 'none';
        });

        syncAppearanceControls();
        update();

        if (app.dataset.role !== 'admin') {
            loadStaffBootstrap();
        }

        // Admin bootstrap
        if (app.dataset.role === 'admin') {
            api('/api/admin/bootstrap')
                .then((data) => {
                    app._adminBootstrap = data;
                    const setMetric = (label, value) => {
                        app.querySelectorAll('.metric-grid article').forEach((art) => {
                            if (art.querySelector('span')?.textContent?.trim() === label) {
                                const strong = art.querySelector('strong');
                                if (strong) strong.textContent = value;
                            }
                        });
                    };
                    if (data.metrics) {
                        setMetric('Active laundry', String(data.metrics.active_laundry));
                        setMetric('Today’s sales', money(data.metrics.today_sales));
                        setMetric('Low stock', String(data.metrics.low_stock_count));
                        setMetric('Total sales', money(data.metrics.today_sales));
                        setMetric('Total expenses', money(data.metrics.today_expenses));
                        setMetric('Net amount', money(data.metrics.net));
                    }
                })
                .catch((err) => console.warn('SSK admin bootstrap:', err.message));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('livewire:navigated', () => {
        const app = document.querySelector('[data-staff-app]');
        if (app) delete app.dataset.sskBound;
        boot();
    });
})();
