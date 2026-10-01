/**
 * Staff bootstrap: load DB data + render module tables
 */
import { api, fmtDate, fmtTime } from './core.js';
import { statusLabel, statusClass } from './queue.js';

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

export function renderStaffLists(app, data, updateFn) {
    const q = app.querySelector('[data-queue-rows]');
    if (q) {
        const list = data.active_laundry || [];
        q.innerHTML = list.length
            ? list
                  .map((tx) => {
                      const basket = tx.basket_tag?.code || '#' + tx.id;
                      const cust = tx.customer?.name || '—';
                      const svc = tx.service?.name || tx.transaction_type || '—';
                      const machine = tx.machine?.name ? `<br><small>${tx.machine.name}</small>` : '';
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
                      const actions =
                          forwardBtn || undoBtn
                              ? `${forwardBtn}${undoBtn}`
                              : '<span class="queue-action-empty">—</span>';
                      return `<div class="order-row"><strong>${basket}</strong><span>${cust}</span><span>${svc}${machine}</span><span><em class="status ${statusClass(tx.status)}">${statusLabel(tx.status)}</em></span><span class="queue-action-cell">${actions}</span></div>`;
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
                      return `<div class="order-row" data-customer-row data-name="${name.toLowerCase()}" data-contact="${(c.contact_number || '').toLowerCase()}" data-email="${(c.email || '').toLowerCase()}">
                        <strong>${name}</strong>
                        <span>${phone}</span>
                        <span>${email}</span>
                        <span>${orders}</span>
                        <span>${last}</span>
                        <span class="customer-actions">
                          <button type="button" class="action-btn-primary" data-do-laundry="${c.id}" data-customer-name="${name.replace(/"/g, '&quot;')}" data-customer-phone="${(c.contact_number || '').replace(/"/g, '&quot;')}" title="Start a laundry order">Do Laundry →</button>
                          <button type="button" class="action-btn-secondary" data-edit-customer="${c.id}" data-customer-name="${name.replace(/"/g, '&quot;')}" data-customer-phone="${(c.contact_number || '').replace(/"/g, '&quot;')}" data-customer-email="${(c.email || '').replace(/"/g, '&quot;')}" title="Edit phone or email">Edit</button>
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

    const mg = app.querySelector('[data-machine-grid]');
    if (mg) {
        const list = data.machines || [];
        mg.innerHTML = list
            .map((m) => {
                const avail = m.status === 'available';
                return `<article class="staff-card" data-machine-id="${m.id}"><span>${m.name}</span><strong class="${avail ? 'available' : ''}">${m.status.replace(/_/g, ' ')}</strong><small>${m.type}</small>
                <button type="button" class="action-btn-secondary" data-machine-status="${m.id}" data-set-machine="${avail ? 'maintenance' : 'available'}" title="Click to change machine status">${avail ? 'Set maintenance →' : 'Set available →'}</button></article>`;
            })
            .join('');
    }

    const fillSelect = (sel, items) => {
        if (!sel) return;
        sel.innerHTML = (items || [])
            .map((i) => `<option value="${i.id}">${i.name} (${i.quantity_on_hand} ${i.unit})</option>`)
            .join('');
    };
    fillSelect(document.querySelector('[data-archive-item]'), data.inventory);

    renderAttendance(app, data);
}

export function loadStaffBootstrap(app, updateFn) {
    return api('/api/staff/bootstrap')
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
                                    <div><strong>${snack.name}</strong><small>₱${snack.unit_price} · ${snack.unit} · ${snack.quantity_on_hand} stock</small></div>
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
            if (list) {
                const avail = data.available_baskets || [];
                list.innerHTML = avail.length
                    ? avail
                          .map(
                              (b) =>
                                  `<li><button type="button" data-basket-option="${b.code}">${b.code} <small>${b.status}</small></button></li>`
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

            // Optional notification panel if present
            const notifBox = app.querySelector('[data-notification-list]');
            if (notifBox) {
                const notes = data.notifications || [];
                notifBox.innerHTML = notes.length
                    ? notes
                          .map(
                              (n) =>
                                  `<div class="dash-alert"><strong>${(n.type || 'alert').replace(/_/g, ' ')}</strong><small>${n.message}</small></div>`
                          )
                          .join('')
                    : '<p class="dash-empty">No notifications.</p>';
            }
            const notifCount = app.querySelector('[data-notification-count]');
            if (notifCount) {
                const n = (data.notifications || []).length;
                notifCount.textContent = String(n);
                notifCount.hidden = n === 0;
            }

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
                    div.innerHTML = `<span>${g.name}</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>0</output><button data-garment-increase type="button">+</button></div>`;
                    if (addBtn) gGrid.insertBefore(div, addBtn);
                    else gGrid.appendChild(div);
                });
            }

            renderStaffLists(app, data, updateFn);
            if (typeof updateFn === 'function') updateFn();
        })
        .catch((err) => console.warn('SSK bootstrap:', err.message));
}
