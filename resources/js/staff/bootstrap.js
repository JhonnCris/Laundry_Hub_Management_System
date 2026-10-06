/**
 * Staff bootstrap: load DB data + render module tables
 */
import { api, errorText, esc, fmtDate, fmtTime, paginate } from './core.js';
import { showScreen } from './navigation.js';
import { statusLabel, statusClass } from './queue.js';

/** Staff can use Manage Customer and Transactions only while clocked in (and not yet clocked out). */
function applyDutyState(app, onDuty) {
    if (app.dataset.role === 'admin') return;
    app.dataset.onDuty = onDuty ? '1' : '0';
    app.querySelectorAll('[data-needs-duty]').forEach((btn) => btn.classList.toggle('is-locked', !onDuty));
    const lock = app.querySelector('[data-duty-lock]');
    const guide = app.querySelector('[data-duty-guide]');
    if (lock) lock.hidden = onDuty;
    if (guide) guide.hidden = !onDuty;
    const open = app.querySelector('.prototype-panel.is-visible');
    if (!onDuty && open && ['customers', 'transactions'].includes(open.dataset.panel)) showScreen(app, 'attendance');
}

export function renderAttendance(app, data) {
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
    const clockedIn = !!(today && today.clock_in);
    const clockedOut = !!(today && today.clock_out);
    const inBtn = app.querySelector('[data-clock-in]');
    const outBtn = app.querySelector('[data-clock-out]');
    if (inBtn) {
        inBtn.disabled = clockedIn;
        inBtn.title = clockedIn ? 'Already clocked in today' : 'Start your shift';
    }
    if (outBtn) {
        outBtn.disabled = !clockedIn || clockedOut;
        outBtn.title = !clockedIn ? 'Clock in first' : clockedOut ? 'Already clocked out today' : 'End your shift';
    }
    applyDutyState(app, clockedIn && !clockedOut);
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
}

/** Baskets table, narrowed by the In use / Available filter. */
export function renderBaskets(app, data) {
    const br = app.querySelector('[data-basket-rows]');
    if (!br) return;
    const filter = app._basketFilter || '';
    const all = data.baskets || [];
    const list = all.filter((b) => {
        const inUse = !!b.assigned || b.status !== 'available';
        return !filter || (filter === 'in_use' ? inUse : !inUse);
    });
    br.innerHTML = list.length
        ? list
              .map((b) => {
                  const inUse = !!b.assigned || b.status !== 'available';
                  const cls = inUse ? 'processing' : 'ready';
                  const a = b.assigned;
                  const who = a
                      ? `<span><strong>${esc(a.customer || 'Customer')}</strong></span>`
                      : b.status === 'in_use'
                        ? '<span style="color:var(--staff-muted)">In use · no open order</span>'
                        : '<span style="color:var(--staff-muted)">—</span>';
                  const orderCell = a
                      ? `<span>#${esc(a.order_id)} · <em class="status ${statusClass(a.order_status)}">${esc(statusLabel(a.order_status))}</em></span>`
                      : '<span></span>';
                  return `<div class="order-row"><strong>${esc(b.code)}</strong><span><em class="status ${cls}">${inUse ? 'in use' : 'available'}</em></span>${who}${orderCell}</div>`;
              })
              .join('')
        : `<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">${all.length ? `No ${filter === 'in_use' ? 'baskets in use' : 'available baskets'}.` : 'No baskets yet. Click Add basket to register one.'}</span></div>`;
    paginate(br);
}

/** Counts down every machine timer on screen; one interval for the whole page. */
function tickMachineTimers() {
    const paint = () => {
        const grid = document.querySelector('[data-machine-grid]');
        if (!grid) return;
        const skew = Number(grid.dataset.skew || 0);
        grid.querySelectorAll('[data-ends-at]').forEach((el) => {
            if (!el.dataset.endsAt) return;
            const left = Math.round((new Date(el.dataset.endsAt).getTime() - (Date.now() + skew)) / 1000);
            if (left <= 0) {
                el.textContent = "Time's up · mark the order ready";
                return;
            }
            const m = Math.floor(left / 60);
            const sec = String(left % 60).padStart(2, '0');
            el.textContent = `Time left ${m}:${sec}`;
        });
    };
    paint();
    if (!window._machineTimer) window._machineTimer = setInterval(paint, 1000);
}

export function renderStaffLists(app, data) {
    const q = app.querySelector('[data-queue-rows]');
    if (q) {
        const list = data.active_laundry || [];
        q.innerHTML = list.length
            ? list
                  .map((tx) => {
                      const basket = tx.basket_tag?.code || '#' + tx.id;
                      const cust = tx.customer?.name || '—';
                      const svc = tx.service?.name || tx.transaction_type || '—';
                      const machine = tx.machine?.name ? `<br><small>${esc(tx.machine.name)}</small>` : '';
                      const next =
                          tx.status === 'pending'
                              ? 'processing'
                              : tx.status === 'processing'
                                ? 'ready_for_pickup'
                                : tx.status === 'ready_for_pickup'
                                  ? 'claimed'
                                  : null;
                      const prev =
                          tx.status === 'processing'
                              ? 'pending'
                              : tx.status === 'ready_for_pickup'
                                ? 'processing'
                                : null;
                      const forwardBtn = next
                          ? `<button type="button" class="queue-action-btn${next === 'claimed' ? ' is-danger' : ''}" data-set-status="${tx.id}" data-next-status="${next}" title="Move to next status">Mark ${statusLabel(next)} →</button>`
                          : '';
                      const undoBtn = prev
                          ? `<button type="button" class="queue-undo-btn" data-set-status="${tx.id}" data-next-status="${prev}" data-undo="1" title="Undo last status change">Undo → ${statusLabel(prev)}</button>`
                          : '';
                      const cancelBtn = `<button type="button" class="queue-cancel-btn" data-cancel-order="${tx.id}" title="Cancel this order and return its stock">Cancel order</button>`;
                      const actions = `${forwardBtn}${undoBtn}${cancelBtn}`;
                      return `<div class="order-row"><strong>${esc(basket)}</strong><span>${esc(cust)}</span><span>${esc(svc)}${machine}</span><span><em class="status ${statusClass(tx.status)}">${statusLabel(tx.status)}</em></span><span class="queue-action-cell">${actions}</span></div>`;
                  })
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1">No active laundry orders.</span></div>';
    }

    const cr = app.querySelector('[data-customer-rows]');
    if (cr) {
        const list = data.customers || [];
        cr.innerHTML = list.length
            ? list
                  .map((c) => {
                      const name = c.name || '—';
                      const phone = c.contact_number || '—';
                      const email = c.email || '—';
                      const orders = c.orders_count ?? 0;
                      const last = c.last_service ? fmtDate(c.last_service) : '—';
                      return `<div class="order-row" data-customer-row data-name="${esc(name.toLowerCase())}" data-contact="${esc((c.contact_number || '').toLowerCase())}" data-email="${esc((c.email || '').toLowerCase())}">
                        <strong>${esc(name)}</strong>
                        <span>${esc(phone)}</span>
                        <span>${esc(email)}</span>
                        <span>${orders}</span>
                        <span>${last}</span>
                        <span class="customer-actions">
                          <button type="button" class="action-btn-primary" data-do-laundry="${c.id}" data-customer-name="${esc(name)}" data-customer-phone="${esc(c.contact_number || '')}" title="Start a laundry order">Do Laundry →</button>
                          <button type="button" class="action-btn-secondary" data-edit-customer="${c.id}" data-customer-name="${esc(name)}" data-customer-phone="${esc(c.contact_number || '')}" data-customer-email="${esc(c.email || '')}" title="Edit phone or email">Edit</button>
                        </span>
                      </div>`;
                  })
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1">No customers yet. Click Add New Customer to begin.</span></div>';
    }

    const ir = app.querySelector('[data-inventory-rows]');
    if (ir) {
        const list = data.inventory || [];
        ir.innerHTML = list.length
            ? list
                  .map((i) => {
                      const low = i.quantity_on_hand <= i.low_stock_threshold;
                      const reported = (data.reported_low_stock_ids || []).includes(i.id);
                      const alertCell = low
                          ? `<button type="button" class="outline-action notify-low-btn" data-notify-low="${i.id}" ${reported ? 'disabled' : ''} title="Tell the admin this item is running low">${reported ? 'Admin notified ✓' : 'Notify admin'}</button>`
                          : '<span></span>';
                      return `<div class="order-row inv5${low ? ' is-low' : ''}"><strong>${low ? '<i class="low-dot" title="Low stock"></i>' : ''}${esc(i.name)}</strong><span>${esc(i.category?.name || '—')}</span><span>${i.quantity_on_hand} ${esc(i.unit)}</span><em class="status ${low ? 'pending' : 'ready'}">${low ? 'Low stock' : 'In stock'}</em>${alertCell}</div>`;
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
                      return `<div class="order-row"><strong>${esc(i.name)}</strong><span>${esc(i.category?.name || '—')}</span><span>${esc(reason)}</span><span>${i.quantity_on_hand}</span></div>`;
                  })
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No archived items.</span></div>';
    }

    renderBaskets(app, data);

    const mg = app.querySelector('[data-machine-grid]');
    if (mg) {
        const list = data.machines || [];
        const skew = data.server_now ? new Date(data.server_now).getTime() - Date.now() : 0;
        mg.dataset.skew = String(skew);
        mg.innerHTML = list
            .map((m) => {
                const avail = m.status === 'available';
                const o = m.current_order;
                const using = m.status === 'in_use';
                const usage = using && o
                    ? `<small>Order #${o.id} · ${esc(o.customer || '—')} · Self service</small><small class="machine-eta" data-ends-at="${esc(o.ends_at || '')}">${o.ends_at ? '' : 'No timer set'}</small>`
                    : '';
                const btn = using
                    ? ''
                    : `<button type="button" class="action-btn-secondary" data-machine-status="${m.id}" data-set-machine="${avail ? 'maintenance' : 'available'}" title="Click to change machine status">${avail ? 'Set maintenance →' : 'Set available →'}</button>`;
                return `<article class="staff-card" data-machine-id="${m.id}"><span>${esc(m.name)}</span><strong class="${avail ? 'available' : ''}">${esc(m.status.replace(/_/g, ' '))}</strong><small>${esc(m.type)}</small>${usage}${btn}</article>`;
            })
            .join('');
        tickMachineTimers();
    }

    ['[data-queue-rows]', '[data-customer-rows]', '[data-inventory-rows]', '[data-archived-rows]'].forEach((sel) => paginate(app.querySelector(sel)));

    const fillSelect = (sel, items) => {
        if (!sel) return;
        sel.innerHTML = (items || [])
            .map((i) => `<option value="${esc(i.id)}">${esc(i.name)} (${i.quantity_on_hand} ${esc(i.unit)})</option>`)
            .join('');
    };
    fillSelect(document.querySelector('[data-archive-item]'), data.inventory);

    renderAttendance(app, data);
}

export function loadStaffBootstrap(app, updateFn) {
    return api('/ajax/staff/bootstrap')
        .then((data) => {
            app._bootstrap = data;
            const snackList = app.querySelector('[data-snack-list]');
            if (snackList) {
                const snacks = data.snacks || [];
                snackList.innerHTML = snacks.length
                    ? snacks
                          .map(
                              (snack) =>
                                  `<div class="add-on" data-item-id="${snack.id}" data-price="${snack.unit_price}">
                                    <div><strong>${esc(snack.name)}</strong><small>₱${snack.unit_price} · ${esc(snack.unit)} · ${snack.quantity_on_hand} stock</small></div>
                                    <div class="quantity-control"><button data-decrease type="button">−</button><output>0</output><button data-increase type="button">+</button></div>
                                  </div>`
                          )
                          .join('')
                    : '<p class="dash-empty" style="padding:8px 0;margin:0">No snacks/drinks in inventory yet.</p>';
            }
            app.querySelectorAll('[data-consumable]').forEach((select) => {
                select.innerHTML = '<option value="0" data-unit-price="0">None</option>';
                (data.detergents || []).forEach((d) => {
                    const o = document.createElement('option');
                    o.value = String(d.unit_price);
                    o.dataset.unitPrice = String(d.unit_price);
                    o.dataset.itemId = String(d.id);
                    o.textContent = `${d.name} · ₱${d.unit_price} each · ${d.quantity_on_hand} left`;
                    select.appendChild(o);
                });
            });
            app.querySelectorAll('[data-service-price][data-pricing="flat"]').forEach((select) => {
                if (!(data.services || []).length) return;
                select.innerHTML = '<option value="0" data-none="1">None (snacks &amp; drinks only)</option>';
                data.services.forEach((svc) => {
                    const o = document.createElement('option');
                    o.value = String(svc.base_price);
                    o.dataset.serviceId = String(svc.id);
                    o.textContent = `${svc.name} · ₱${svc.base_price}`;
                    select.appendChild(o);
                });
            });
            app.querySelectorAll('[data-service-price][data-pricing="per_kg"] option[data-service-name]').forEach((o) => {
                const match = (data.services || []).find((svc) => svc.name === o.dataset.serviceName);
                if (match) o.dataset.serviceId = String(match.id);
            });
            const machineSelect = app.querySelector('[data-machine]');
            if (machineSelect && (data.machines || []).length) {
                machineSelect.innerHTML = '';
                data.machines.filter((m) => m.status === 'available').forEach((m) => {
                    const o = document.createElement('option');
                    o.value = m.name;
                    o.dataset.machineId = String(m.id);
                    o.textContent = m.name;
                    machineSelect.appendChild(o);
                });
                if (!machineSelect.options.length) machineSelect.innerHTML = '<option value="">No machine available</option>';
            }
            const list = app.querySelector('[data-basket-list]');
            if (list) {
                const avail = data.available_baskets || [];
                list.innerHTML = avail.length
                    ? avail
                          .map(
                              (b) =>
                                  `<li><button type="button" data-basket-option="${esc(b.code)}">${esc(b.code)} <small>${esc(b.status)}</small></button></li>`
                          )
                          .join('')
                    : '<li><span style="padding:8px;color:var(--staff-muted);font-size:13px">No available baskets. Add one in Inventory.</span></li>';
            }

            // Nav badges from live counts (hide when zero)
            const setBadge = (key, count) => {
                const el = app.querySelector(`[data-nav-badge="${key}"]`);
                if (!el) return;
                const n = Number(count) || 0;
                el.textContent = String(n);
                if (n > 0) {
                    el.hidden = false;
                    el.removeAttribute('hidden');
                } else {
                    el.hidden = true;
                    el.setAttribute('hidden', '');
                }
            };
            setBadge('queue', data.active_laundry_count ?? (data.active_laundry || []).length);
            setBadge(
                'inventory',
                data.low_stock_count ??
                    (data.inventory || []).filter(
                        (i) =>
                            i.low_stock_threshold != null &&
                            Number(i.quantity_on_hand) <= Number(i.low_stock_threshold)
                    ).length
            );
            setBadge('archived', (data.archived_inventory || []).length);

            // Default basket: first available, not a hardcoded tag
            const basketInput = app.querySelector('[data-basket-tag]');
            if (basketInput && !basketInput.value) {
                const first = (data.available_baskets || [])[0];
                if (first) basketInput.value = first.code;
            }

            // Garment types from DB if grid supports rebuild
            const gGrid = app.querySelector('[data-garment-grid]');
            if (gGrid && (data.garment_types || []).length) {
                const addBtn = gGrid.querySelector('[data-add-garment]');
                const existing = new Set(
                    [...gGrid.querySelectorAll('.garment-counter span')].map((el) =>
                        el.textContent.trim().toLowerCase()
                    )
                );
                (data.garment_types || []).forEach((g) => {
                    if (existing.has(String(g.name).toLowerCase())) return;
                    const div = document.createElement('div');
                    div.className = 'garment-counter';
                    div.innerHTML = `<span>${esc(g.name)}</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>0</output><button data-garment-increase type="button">+</button></div>`;
                    if (addBtn) gGrid.insertBefore(div, addBtn);
                    else gGrid.appendChild(div);
                });
            }

            renderStaffLists(app, data);
            if (typeof updateFn === 'function') updateFn();
        })
        .catch((err) => {
            console.warn('SSK bootstrap:', err.message);
            const notice = `<div class="order-row"><span style="grid-column:1/-1;color:#af3b2c">Could not load data: ${esc(errorText(err))}</span></div>`;
            ['[data-customer-rows]', '[data-queue-rows]', '[data-inventory-rows]', '[data-basket-rows]', '[data-archived-rows]', '[data-attendance-rows]'].forEach((sel) => {
                const el = app.querySelector(sel);
                if (el) el.innerHTML = notice;
            });
        });
}
