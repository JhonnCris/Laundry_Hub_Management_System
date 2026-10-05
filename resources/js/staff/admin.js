/**
 * Admin: dashboard KPIs, filters, export + stock receiving
 */
import { api, esc, money } from './core.js';

function statusLabel(st) {
    const map = {
        pending: 'Pending',
        processing: 'Processing',
        ready_for_pickup: 'Ready for pickup',
        claimed: 'Claimed',
        cancelled: 'Cancelled',
    };
    return map[st] || st || '—';
}

function statusClass(st) {
    if (st === 'processing') return 'processing';
    if (st === 'ready_for_pickup') return 'ready';
    if (st === 'pending') return 'pending';
    if (st === 'claimed') return 'ready';
    return 'pending';
}

const H = (cells) => Object.assign(cells, { isHeader: true });

function downloadCsv(filename, rows) {
    const csv = rows
        .map((r) =>
            r
                .map((c) => {
                    let s = String(c ?? '');
                    // Neutralise spreadsheet formulas (CSV injection)
                    if (/^[=+\-@\t\r]/.test(s) && Number.isNaN(Number(s))) s = "'" + s;
                    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
                })
                .join(',')
        )
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}


function renderBarChart(container, items, { valueKey = 'amount', labelKey = 'date', expense = false } = {}) {
    if (!container) return;
    if (!items || !items.length) {
        container.innerHTML = '<p class="chart-empty">No data in this range.</p>';
        return;
    }
    const max = Math.max(...items.map((i) => Number(i[valueKey]) || 0), 1);
    container.innerHTML = items
        .map((i) => {
            const val = Number(i[valueKey]) || 0;
            const h = Math.max(4, Math.round((val / max) * 120));
            const label = String(i[labelKey] || '').length > 10
                ? String(i[labelKey]).slice(5) // MM-DD
                : String(i[labelKey] || '');
            const cls = expense ? 'bar is-expense' : 'bar';
            return `<div class="chart-bar"><span class="bar-value">${val ? money(val).replace('₱', '₱') : '—'}</span><div class="${cls}" style="height:${h}px"></div><span class="bar-label" title="${esc(i[labelKey] || '')}">${esc(label)}</span></div>`;
        })
        .join('');
}

/** Horizontal ranking: best seller first, bar length = units sold. */
function renderRankBars(container, items) {
    if (!container) return;
    if (!items || !items.length) {
        container.innerHTML = '<p class="chart-empty">No product sales in this range.</p>';
        return;
    }
    const ranked = [...items].sort((a, b) => (Number(b.qty_sold) || 0) - (Number(a.qty_sold) || 0));
    const max = Math.max(Number(ranked[0].qty_sold) || 0, 1);
    container.innerHTML = ranked
        .map((p, i) => {
            const qty = Number(p.qty_sold) || 0;
            const w = Math.max(3, Math.round((qty / max) * 100));
            return `<div class="rank-row"><span class="rank-no">${i + 1}</span><span class="rank-name" title="${esc(p.name)}">${esc(p.name)}</span><div class="rank-track"><div class="rank-fill" style="width:${w}%"></div></div><span class="rank-qty">${qty} sold</span></div>`;
        })
        .join('');
}

const PIE_COLORS = ['#1a4d3e', '#2d6a4f', '#52b788', '#95d5b2', '#e9c46a', '#f4a261', '#e76f51', '#457b9d', '#8d99ae', '#b5838d'];

/** Donut of units sold per product with a colour-keyed legend. */
function renderProductPie(container, items) {
    if (!container) return;
    const list = (items || []).filter((p) => Number(p.qty_sold) > 0);
    if (!list.length) {
        container.innerHTML = '<p class="chart-empty">No product sales in this range.</p>';
        return;
    }
    const total = list.reduce((s, p) => s + Number(p.qty_sold), 0);
    let acc = 0;
    const stops = list.map((p, i) => {
        const from = (acc / total) * 100;
        acc += Number(p.qty_sold);
        return `${PIE_COLORS[i % PIE_COLORS.length]} ${from}% ${(acc / total) * 100}%`;
    });
    const legend = list
        .map((p, i) => {
            const pct = Math.round((Number(p.qty_sold) / total) * 100);
            return `<li><i style="background:${PIE_COLORS[i % PIE_COLORS.length]}"></i><span class="pie-name">${esc(p.name)}</span><span class="pie-val">${p.qty_sold} · ${pct}% · ${money(p.revenue)}</span></li>`;
        })
        .join('');
    container.innerHTML = `<div class="pie-wrap"><div class="pie" role="img" aria-label="Units sold per product" style="background:conic-gradient(${stops.join(',')})"><span>${total}<small>sold</small></span></div><ul class="pie-legend">${legend}</ul></div>`;
}

function rangeQuery(app) {
    const from =
        app.querySelector('[data-sum-from]')?.value ||
        app.querySelector('[data-dash-from]')?.value ||
        '';
    const to =
        app.querySelector('[data-sum-to]')?.value ||
        app.querySelector('[data-dash-to]')?.value ||
        '';
    const q = new URLSearchParams();
    if (from) q.set('from', from);
    if (to) q.set('to', to);
    const s = q.toString();
    return s ? `?${s}` : '';
}

function setDefaultDates(app, fromStr, toStr) {
    app.querySelectorAll('[data-dash-from], [data-sum-from]').forEach((el) => {
        if (fromStr) el.value = fromStr;
    });
    app.querySelectorAll('[data-dash-to], [data-sum-to]').forEach((el) => {
        if (toStr) el.value = toStr;
    });
}


function renderBusyHoursChart(app, data) {
    const hours = (data.analytics || {}).orders_by_hour || [];
    const busyEl = app.querySelector('[data-chart-busy-hours]');
    const note = app.querySelector('[data-busy-peak-note]');
    if (!busyEl) return;

    if (!hours.length) {
        busyEl.innerHTML = '<p class="chart-empty">No order data yet. Complete transactions to see peak hours.</p>';
        if (note) note.textContent = '';
        return;
    }

    const max = Math.max(...hours.map((h) => Number(h.count) || 0), 1);
    const peak = hours.reduce((a, b) => (Number(b.count) > Number(a.count) ? b : a), hours[0]);
    const quiet = hours.filter((h) => Number(h.count) > 0);
    const quietest = quiet.length
        ? quiet.reduce((a, b) => (Number(b.count) < Number(a.count) ? b : a), quiet[0])
        : null;

    // Prefer business hours 6–21 if data exists there, else full day
    let list = hours;
    const biz = hours.filter((h) => {
        const hr = parseInt(String(h.hour).slice(0, 2), 10);
        return hr >= 6 && hr <= 21;
    });
    if (biz.some((h) => h.count > 0)) list = biz;

    busyEl.innerHTML = list
        .map((h) => {
            const val = Number(h.count) || 0;
            const ht = val > 0 ? Math.max(6, Math.round((val / max) * 140)) : 4;
            const isPeak = h.hour === peak.hour && val > 0;
            const barCls = isPeak ? 'bar is-peak' : val > 0 ? 'bar' : 'bar is-muted';
            const label = String(h.hour).replace(':00', '');
            return `<div class="chart-bar"><span class="bar-value">${val > 0 ? val : ''}</span><div class="${barCls}" style="height:${ht}px" title="${h.hour}: ${val} order(s)"></div><span class="bar-label">${esc(label)}</span></div>`;
        })
        .join('');

    if (note) {
        if (max <= 0) {
            note.textContent = 'No orders in this range.';
        } else {
            const peakLabel = String(peak.hour);
            note.textContent = `Peak: ${peakLabel} with ${peak.count} order(s).${
                quietest ? ` Quietest among active hours: ${quietest.hour}.` : ''
            }`;
        }
    }
}

function renderServiceShare(container, mix, mode = 'orders') {
    if (!container) return;
    const list = mix || [];
    if (!list.length) {
        container.innerHTML = '<p class="dash-empty">No service data in this range.</p>';
        return;
    }
    const total = list.reduce((s, x) => s + Number(mode === 'revenue' ? x.revenue : x.orders) || 0, 0) || 1;
    container.innerHTML = list
        .map((x) => {
            const val = Number(mode === 'revenue' ? x.revenue : x.orders) || 0;
            const pct = Math.round((val / total) * 100);
            const right =
                mode === 'revenue' ? `${pct}% · ${money(x.revenue)}` : `${pct}%`;
            return `<div class="share-row"><span>${esc(x.service)}</span><span class="share-pct">${right}</span><div class="share-bar-track"><div class="share-bar-fill" style="width:${pct}%"></div></div></div>`;
        })
        .join('');
}

function renderUsers(app, data) {
    const users = data.users || [];
    const admins = users.filter((u) => u.role === 'admin').length;
    const staff = users.filter((u) => u.role === 'staff').length;
    const set = (sel, v) => {
        const el = app.querySelector(sel);
        if (el) el.textContent = String(v);
    };
    set('[data-users-total]', users.length);
    set('[data-users-admins]', admins);
    const pending = users.filter((u) => u.role === 'pending').length;
    set('[data-users-staff]', staff);
    set('[data-users-pending]', pending ? `${pending} pending approval` : 'Accounts');
    const rows = app.querySelector('[data-admin-user-rows]');
    if (rows) {
        rows.innerHTML = users.length
            ? users
                  .map((u) => {
                      const role = u.role === 'admin' ? 'Admin' : u.role === 'pending' ? 'Pending' : 'Staff';
                      const isPending = u.role === 'pending';
                      const approve = isPending
                          ? `<button type="button" class="action-btn-primary" data-approve-user="${u.id}" title="Approve as staff">Approve</button>`
                          : '';
                      return `<div class="order-row"><strong>${esc(u.name)}</strong><span>${esc(u.email)}</span><span>${esc(role)}</span><em class="status ${isPending ? 'pending' : 'ready'}">${isPending ? 'Pending approval' : 'Active'}</em><span class="user-actions">${approve}<button type="button" class="action-btn-secondary" data-edit-user="${u.id}" title="Edit user">Edit</button></span></div>`;
                  })
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1">No users yet.</span></div>';
    }
    const catSel = document.querySelector('[data-new-item-category]');
    if (catSel && (data.categories || []).length) {
        catSel.innerHTML = data.categories
            .map((c) => `<option value="${c.id}">${esc(c.name)}</option>`)
            .join('');
    }
}


function renderDashboard(app, data) {
    const m = data.metrics || {};
    const bd = m.status_breakdown || {};
    const setText = (sel, val) => {
        const el = app.querySelector(sel);
        if (el) el.textContent = val;
    };

    setText('[data-kpi-active-value]', String(m.active_laundry ?? 0));
    setText(
        '[data-kpi-active-sub]',
        `${bd.processing || 0} processing · ${bd.ready_for_pickup || 0} ready · ${bd.pending || 0} pending`
    );
    setText('[data-kpi-stock-value]', String(m.low_stock_count ?? 0));
    setText(
        '[data-kpi-stock-sub]',
        (m.low_stock_count || 0) > 0 ? 'Action required' : 'All healthy'
    );
    const machineTotal = (data.machines || []).length;
    const attn = Number(m.machines_attention) || 0;
    setText('[data-kpi-machines-value]', machineTotal ? `${attn} of ${machineTotal}` : String(attn));
    setText(
        '[data-kpi-machines-sub]',
        (m.machines_attention || 0) > 0 ? 'Need attention' : 'All available'
    );

    // Summary panel metrics (same period)
    const setMetricByLabel = (label, value) => {
        app.querySelectorAll('[data-panel="summary"] .metric-grid article').forEach((art) => {
            if (art.querySelector('span')?.textContent?.trim() === label) {
                const strong = art.querySelector('strong');
                if (strong) strong.textContent = value;
            }
        });
    };
    setMetricByLabel('Total sales', money(m.period_sales ?? 0));
    setMetricByLabel('Total expenses', money(m.period_expenses ?? m.today_expenses ?? 0));
    setMetricByLabel('Net amount', money(m.net ?? 0));

    const kpi = app._dashKpi || 'active';
    renderActivityTable(app, data, kpi);
    renderAlerts(app, data);
    renderFinanceTable(app, data);
    renderActivityModalList(app, data);
    renderBusyHoursChart(app, data);
    renderBarChart(app.querySelector('[data-chart-dash-sales]'), (data.analytics || {}).sales_by_day || [], {
        valueKey: 'amount',
        labelKey: 'date',
    });
    renderServiceShare(app.querySelector('[data-dash-service-share]'), (data.analytics || {}).service_mix || [], 'orders');
}



function filterOrders(data, kpi) {
    const orders = (kpi === 'active' ? data.active_orders : data.recent_orders) || [];
    if (kpi === 'active') {
        return orders.filter((o) =>
            ['pending', 'processing', 'ready_for_pickup'].includes(o.status)
        );
    }
    if (kpi === 'stock') return [];
    if (kpi === 'machines') return [];
    return orders;
}

function renderActivityTable(app, data, kpi) {
    const rows = app.querySelector('[data-dash-order-rows]');
    const title = app.querySelector('[data-dash-activity-title]');
    if (!rows) return;

    if (kpi === 'stock') {
        if (title) title.textContent = 'Low stock items';
        const items = data.low_stock || [];
        rows.previousElementSibling?.classList; // keep head
        const head = rows.parentElement?.querySelector('.order-head');
        if (head) {
            head.innerHTML =
                '<span>Item</span><span>Category</span><span>On hand</span><span>Threshold</span><span>Status</span>';
        }
        rows.innerHTML = items.length
            ? items
                  .map(
                      (i) =>
                          `<div class="order-row"><strong>${esc(i.name)}</strong><span>${esc(i.category?.name || '—')}</span><span>${i.quantity_on_hand} ${esc(i.unit || '')}</span><span>${i.low_stock_threshold ?? '—'}</span><em class="status pending">Low stock</em></div>`
                  )
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1">No low-stock items.</span></div>';
        return;
    }

    if (kpi === 'machines') {
        if (title) title.textContent = 'Machines needing attention';
        const head = rows.parentElement?.querySelector('.order-head');
        if (head) {
            head.innerHTML =
                '<span>Machine</span><span>Type</span><span>Status</span><span></span><span></span>';
        }
        const list = data.machines_attention || [];
        rows.innerHTML = list.length
            ? list
                  .map(
                      (m) =>
                          `<div class="order-row"><strong>${esc(m.name)}</strong><span>${esc(m.type || '—')}</span><em class="status pending">${(m.status || '').replace(/_/g, ' ')}</em><span></span><span></span></div>`
                  )
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1">All machines available.</span></div>';
        return;
    }

    if (title) {
        title.textContent =
            'Active laundry orders';
    }
    const head = rows.parentElement?.querySelector('.order-head');
    if (head) {
        head.innerHTML =
            '<span>Basket</span><span>Customer</span><span>Service</span><span class="num">Amount</span><span>Status</span>';
    }
    const list = filterOrders(data, kpi);
    rows.innerHTML = list.length
        ? list
              .map((tx) => {
                  const basket = tx.basket_tag?.code || '#' + tx.id;
                  const cust = tx.customer?.name || '—';
                  const svc =
                      tx.service?.name ||
                      (tx.transaction_type === 'self_service' ? 'Self Service' : 'Drop Off');
                  const amt = money(tx.total_amount || 0);
                  return `<div class="order-row" data-tx-id="${tx.id}" title="View transaction details"><strong>${esc(basket)}</strong><span>${esc(cust)}</span><span>${esc(svc)}</span><span class="num">${amt}</span><em class="status ${statusClass(tx.status)}">${statusLabel(tx.status)}</em></div>`;
              })
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1">No orders for this view.</span></div>';
}

function renderAlerts(app, data) {
    const box = app.querySelector('[data-dash-alerts]');
    const countEl = app.querySelector('[data-dash-alert-count]');
    if (!box) return;
    const alerts = [];
    (data.staff_reports || []).forEach((r) => {
        alerts.push({ title: 'Staff report: low stock', body: r.message, dismissId: r.id });
    });
    (data.low_stock || []).forEach((i) => {
        alerts.push({
            title: `Low stock: ${i.name}`,
            body: `${i.quantity_on_hand} ${i.unit || ''} left (threshold ${i.low_stock_threshold ?? '—'})`,
        });
    });
    (data.machines_attention || []).forEach((m) => {
        alerts.push({
            title: `Machine: ${m.name}`,
            body: `Status: ${(m.status || '').replace(/_/g, ' ')} · ${m.type || ''}`,
        });
    });
    const m = data.metrics || {};
    if ((m.active_laundry || 0) > 0 && (m.status_breakdown?.ready_for_pickup || 0) > 0) {
        alerts.push({
            title: 'Ready for pickup',
            body: `${m.status_breakdown.ready_for_pickup} order(s) waiting for customer claim`,
        });
    }
    if (countEl) countEl.textContent = `${alerts.length} alert${alerts.length === 1 ? '' : 's'}`;
    box.innerHTML = alerts.length
        ? alerts
              .map(
                  (a) =>
                      a.dismissId
                          ? `<div class="dash-alert has-action"><div><strong>${esc(a.title)}</strong><small>${esc(a.body)}</small></div><button type="button" class="outline-action alert-dismiss" data-dismiss-alert="${a.dismissId}">Dismiss</button></div>`
                          : `<div class="dash-alert"><strong>${esc(a.title)}</strong><small>${esc(a.body)}</small></div>`
              )
              .join('')
        : '<p class="dash-empty">No alerts right now.</p>';
}

function renderFinanceTable(app, data) {
    const panel = app.querySelector('[data-panel="summary"]');
    if (!panel) return;
    let body = panel.querySelector('[data-admin-finance-rows]');
    if (!body) {
        const table = panel.querySelector('.table-card, .staff-card.full-card');
        if (table) {
            // replace static rows after head
            const head = table.querySelector('.order-head');
            if (head) {
                // remove following static order-rows
                let n = head.nextElementSibling;
                while (n) {
                    const next = n.nextElementSibling;
                    if (n.classList?.contains('order-row')) n.remove();
                    n = next;
                }
                body = document.createElement('div');
                body.setAttribute('data-admin-finance-rows', '');
                head.after(body);
            }
        }
    }
    if (!body) return;
    const rows = data.finance || [];
    body.innerHTML = rows.length
        ? rows
              .map((f) => {
                  const type = f.type === 'expense' ? 'Expense' : 'Income';
                  const who = f.staff?.name || '—';
                  return `<div class="order-row"><strong>${type}</strong><span>${esc(f.description || '—')}</span><span>${esc(who)}</span><span class="num">${money(f.amount)}</span></div>`;
              })
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1">No finance rows in this range.</span></div>';
}

function showActivityList() {
    const listView = document.querySelector('[data-activity-view="list"]');
    const detailView = document.querySelector('[data-activity-view="detail"]');
    if (listView) listView.hidden = false;
    if (detailView) detailView.hidden = true;
    const title = document.querySelector('[data-activity-title]');
    const hint = document.querySelector('[data-activity-hint]');
    if (title) title.textContent = 'Activity';
    if (hint) hint.textContent = 'Select a transaction to see full customer and purchase details.';
}

function showActivityDetail() {
    const listView = document.querySelector('[data-activity-view="list"]');
    const detailView = document.querySelector('[data-activity-view="detail"]');
    if (listView) listView.hidden = true;
    if (detailView) detailView.hidden = false;
}

function renderActivityModalList(app, data) {
    const list = document.querySelector('[data-activity-list]');
    if (!list) return;
    showActivityList();
    const orders = data.recent_orders || [];
    list.innerHTML = orders.length
        ? orders
              .slice(0, 40)
              .map((tx) => {
                  const basket = tx.basket_tag?.code || '#' + tx.id;
                  const cust = tx.customer?.name || '—';
                  const svc =
                      tx.service?.name ||
                      (tx.transaction_type === 'self_service' ? 'Self Service' : 'Drop Off');
                  const kind =
                      tx.transaction_type === 'self_service'
                          ? 'Self Service'
                          : Number(tx.total_amount) > 0 && !(tx.service_id || tx.service)
                            ? 'Purchase'
                            : 'Laundry';
                  return `<div class="activity-row" data-activity-tx="${tx.id}" title="View full details">
                    <strong>${esc(basket)}</strong>
                    <span>${esc(cust)}</span>
                    <span>${esc(svc || kind)}</span>
                    <span>${money(tx.total_amount || 0)} · ${statusLabel(tx.status)}</span>
                  </div>`;
              })
              .join('')
        : '<div class="activity-row"><span style="grid-column:1/-1">No transactions in range.</span></div>';
}

function buildActivityDetailHtml(tx) {
    const basket = tx.basket_tag?.code || '#' + tx.id;
    const cust = tx.customer || {};
    const svc =
        tx.service?.name ||
        (tx.transaction_type === 'self_service' ? 'Self Service' : 'Drop Off');
    const isPurchaseOnly =
        !(tx.service_id || tx.service) &&
        (tx.inventory_items || []).length > 0 &&
        !(tx.garment_types || []).length;

    const garments = (tx.garment_types || [])
        .map((g) => {
            const qty = g.pivot?.quantity ?? g.quantity ?? 0;
            return `<div class="ad-line"><span>${esc(g.name)}</span><span>× ${qty}</span></div>`;
        })
        .join('');

    const items = (tx.inventory_items || [])
        .map((i) => {
            const qty = i.pivot?.quantity ?? 0;
            const line = i.pivot?.line_total ?? qty * (i.pivot?.unit_price || 0);
            return `<div class="ad-line"><span>${esc(i.name)} × ${qty}</span><span>${money(line)}</span></div>`;
        })
        .join('');

    const detergent =
        tx.detergent || tx.detergent_item
            ? `<div class="ad-line"><span>${esc((tx.detergent || tx.detergent_item).name)}</span><span>× ${tx.detergent_quantity ?? 0}</span></div>`
            : '';

    const created = tx.created_at ? String(tx.created_at).replace('T', ' ').slice(0, 16) : '—';

    return `
      <div class="ad-head">
        <div>
          <h3>${esc(cust.name || 'Walk-in / unknown')}</h3>
          <small>${esc(cust.contact_number || '')}${esc(cust.email ? ' · ' + cust.email : '')}</small>
        </div>
        <em class="status ${statusClass(tx.status)}">${statusLabel(tx.status)}</em>
      </div>
      <dl>
        <dt>Reference</dt><dd>${esc(basket)}</dd>
        <dt>Type</dt><dd>${isPurchaseOnly ? 'Purchase only' : tx.transaction_type === 'self_service' ? 'Self Service' : 'Drop Off laundry'}</dd>
        <dt>Service</dt><dd>${esc(svc)}</dd>
        <dt>Date</dt><dd>${created}</dd>
        <dt>Staff</dt><dd>${esc(tx.handled_by?.name || tx.handledBy?.name || '—')}</dd>
        <dt>Basket</dt><dd>${esc(tx.basket_tag?.code || '—')}</dd>
        <dt>Machine</dt><dd>${esc(tx.machine?.name || '—')}</dd>
        <dt>Load (kg)</dt><dd>${tx.load_weight_kg != null ? tx.load_weight_kg : '—'}</dd>
        <dt>Payment</dt><dd>${esc(tx.payment_status || '—')} · Cash ${money(tx.cash_tendered || 0)} · Change ${money(tx.change_given || 0)}</dd>
      </dl>
      ${
          garments
              ? `<div class="ad-section"><h4>Garments</h4>${garments}</div>`
              : ''
      }
      ${
          detergent
              ? `<div class="ad-section"><h4>Detergent / softener</h4>${detergent}</div>`
              : ''
      }
      ${
          items
              ? `<div class="ad-section"><h4>Snacks / drinks / extras</h4>${items}</div>`
              : ''
      }
      ${
          tx.notes
              ? `<div class="ad-section"><h4>Notes</h4><p style="margin:0">${esc(tx.notes)}</p></div>`
              : ''
      }
      ${
          tx.status !== 'cancelled' && (tx.status !== 'claimed' || isPurchaseOnly)
              ? `<div class="ad-section"><button type="button" class="outline-action" data-cancel-order="${tx.id}">Cancel order…</button></div>`
              : ''
      }
      <div class="ad-total"><span>Total</span><span>${money(tx.total_amount || 0)}</span></div>
    `;
}


function openKpiDetailModal(title, hint, rowsHtml) {
    const t = document.querySelector('[data-kpi-detail-title]');
    const h = document.querySelector('[data-kpi-detail-hint]');
    const b = document.querySelector('[data-kpi-detail-body]');
    if (t) t.textContent = title || 'Details';
    if (h) h.textContent = hint || '';
    if (b) b.innerHTML = rowsHtml || '<div class="activity-row"><span style="grid-column:1/-1">No data.</span></div>';
    const el = document.querySelector('[data-kpi-detail-modal]');
    if (el) {
        el.hidden = false;
        el.removeAttribute('hidden');
        el.style.display = 'grid';
    }
}

function closeKpiDetailModal() {
    const el = document.querySelector('[data-kpi-detail-modal]');
    if (el) {
        el.hidden = true;
        el.setAttribute('hidden', '');
        el.style.display = 'none';
    }
}

function showSumKpiModal(app, kpi) {
    const data = app._adminBootstrap || {};
    const m = data.metrics || {};
    const finance = data.finance || [];
    if (kpi === 'sales') {
        const income = finance.filter((f) => f.type === 'income');
        const rangeFrom = data.range?.from || '';
        const rangeTo = data.range?.to || '';
        const orders = (data.recent_orders || []).filter((o) => {
            const day = String(o.created_at || '').slice(0, 10);
            return o.payment_status === 'paid' && day >= rangeFrom && day <= rangeTo;
        });
        const rows =
            orders
                .map((tx) => {
                    const basket = tx.basket_tag?.code || '#' + tx.id;
                    const cust = tx.customer?.name || '—';
                    return `<div class="activity-row"><strong>${esc(basket)}</strong><span>${esc(cust)}</span><span>${statusLabel(tx.status)}</span><span>${money(tx.total_amount || 0)}</span></div>`;
                })
                .join('') ||
            income
                .map(
                    (f) =>
                        `<div class="activity-row"><strong>Income</strong><span>${esc(f.description || '—')}</span><span>${esc(f.staff?.name || '—')}</span><span>${money(f.amount)}</span></div>`
                )
                .join('');
        openKpiDetailModal(
            'Total sales',
            `${money(m.period_sales || 0)} · ${m.paid_orders || 0} paid orders in range`,
            rows || '<div class="activity-row"><span style="grid-column:1/-1">No sales in this range.</span></div>'
        );
        return;
    }
    if (kpi === 'expenses') {
        const expense = finance.filter((f) => f.type === 'expense');
        const rows = expense
            .map(
                (f) =>
                    `<div class="activity-row"><strong>Expense</strong><span>${esc(f.description || '—')}</span><span>${esc(f.staff?.name || '—')}</span><span>${money(f.amount)}</span></div>`
            )
            .join('');
        openKpiDetailModal(
            'Total expenses',
            `${money(m.period_expenses || 0)} · includes stock receipts`,
            rows || '<div class="activity-row"><span style="grid-column:1/-1">No expenses in this range.</span></div>'
        );
        return;
    }
    // net
    const rows = finance
        .map((f) => {
            const type = f.type === 'expense' ? 'Expense' : 'Income';
            return `<div class="activity-row"><strong>${type}</strong><span>${esc(f.description || '—')}</span><span>${esc(f.staff?.name || '—')}</span><span>${money(f.amount)}</span></div>`;
        })
        .join('');
    openKpiDetailModal(
        'Net amount',
        `${money(m.net || 0)} · sales ${money(m.period_sales || 0)} − expenses ${money(m.period_expenses || 0)}`,
        rows || '<div class="activity-row"><span style="grid-column:1/-1">No finance rows in this range.</span></div>'
    );
}


function showDashKpiModal(app, kpi) {
    const data = app._adminBootstrap || {};
    const m = data.metrics || {};
    if (kpi === 'active') {
        const list = data.active_orders || [];
        const rows = list
            .map((tx) => {
                const basket = tx.basket_tag?.code || '#' + tx.id;
                return `<div class="activity-row"><strong>${esc(basket)}</strong><span>${esc(tx.customer?.name || '—')}</span><span>${statusLabel(tx.status)}</span><span>${money(tx.total_amount || 0)}</span></div>`;
            })
            .join('');
        openKpiDetailModal(
            'Active laundry',
            `${m.active_laundry || 0} order(s) in progress`,
            rows || '<div class="activity-row"><span style="grid-column:1/-1">No active orders.</span></div>'
        );
        return;
    }
    if (kpi === 'stock') {
        const items = data.low_stock || [];
        const rows = items
            .map(
                (i) =>
                    `<div class="activity-row"><strong>${esc(i.name)}</strong><span>${esc(i.category?.name || '—')}</span><span>${i.quantity_on_hand} ${esc(i.unit || '')}</span><span>threshold ${i.low_stock_threshold ?? '—'}</span></div>`
            )
            .join('');
        openKpiDetailModal(
            'Low stock',
            `${m.low_stock_count || 0} item(s) need attention`,
            rows || '<div class="activity-row"><span style="grid-column:1/-1">Stock levels look healthy.</span></div>'
        );
        return;
    }
    if (kpi === 'machines') {
        const list = data.machines_attention || [];
        const rows = list
            .map(
                (x) =>
                    `<div class="activity-row"><strong>${esc(x.name)}</strong><span>${esc(x.type || '—')}</span><span>${(x.status || '').replace(/_/g, ' ')}</span><span></span></div>`
            )
            .join('');
        openKpiDetailModal(
            'Machines needing attention',
            `${m.machines_attention || 0} of ${(data.machines || []).length} machines`,
            rows || '<div class="activity-row"><span style="grid-column:1/-1">All machines available.</span></div>'
        );
        return;
    }
}

function openActivityDetail(app, txId) {
    const data = app._adminBootstrap || {};
    const local = [...(data.recent_orders || []), ...(data.active_orders || []), ...(data.cancelled_orders || [])].find((o) => String(o.id) === String(txId));
    const paint = (tx) => {
        const box = document.querySelector('[data-activity-detail]');
        const title = document.querySelector('[data-activity-title]');
        const hint = document.querySelector('[data-activity-hint]');
        if (box) box.innerHTML = buildActivityDetailHtml(tx);
        if (title) title.textContent = 'Transaction detail';
        if (hint) {
            hint.textContent =
                (tx.customer?.name || 'Customer') +
                ' · ' +
                (tx.basket_tag?.code || '#' + tx.id);
        }
        showActivityDetail();
        const el = document.querySelector('[data-activity-modal]');
        if (el) {
            el.hidden = false;
            el.removeAttribute('hidden');
        }
    };

    api(`/ajax/admin/transactions/${txId}`)
        .then((r) => paint(r.transaction || r))
        .catch((err) => {
            if (local) paint(local);
            else console.warn(err.message);
        });
}


function renderSummaryAnalytics(app, data) {
    const m = data.metrics || {};
    const an = data.analytics || {};
    const set = (sel, val) => {
        const el = app.querySelector(sel);
        if (el) el.textContent = val;
    };
    set('[data-sum-sales]', money(m.period_sales ?? 0));
    set('[data-sum-expenses]', money(m.period_expenses ?? 0));
    set('[data-sum-net]', money(m.net ?? 0));
    set('[data-sum-sales-sub]', `${m.paid_orders || 0} paid orders`);
    set('[data-sum-expenses-sub]', 'Includes stock receipts');
    set('[data-sum-net-sub]', 'Sales − expenses');
    set('[data-sum-range-label]', `${data.range?.from || '—'} → ${data.range?.to || '—'}`);

    // Daily sales chart
    renderBarChart(app.querySelector('[data-chart-sales-expenses]'), an.sales_by_day || [], {
        valueKey: 'amount',
        labelKey: 'date',
    });


    // Service mix + popular products
    renderServiceShare(app.querySelector('[data-sum-service-mix]'), an.service_mix || [], 'revenue');

    renderProductPie(app.querySelector('[data-popular-product-rows]'), an.popular_products || []);

    renderCancelledArchive(app, data);

    // Sync summary date inputs from range
    const sf = app.querySelector('[data-sum-from]');
    const st = app.querySelector('[data-sum-to]');
    if (sf && data.range?.from) sf.value = data.range.from;
    if (st && data.range?.to) st.value = data.range.to;

    // Finance filter by KPI
    const sumKpi = app._sumKpi || 'net';
    renderFinanceFiltered(app, data, sumKpi);
}

export function renderCancelledArchive(app, data) {
    const rows = app.querySelector('[data-cancelled-rows]');
    const countEl = app.querySelector('[data-cancelled-count]');
    const list = data.cancelled_orders || [];
    const total = data.metrics?.cancelled_orders ?? list.length;
    if (countEl) countEl.textContent = `${total} cancelled`;
    if (!rows) return;
    rows.innerHTML = list.length
        ? list
              .map((tx) => {
                  const basket = tx.basket_tag?.code || '#' + tx.id;
                  const when = tx.updated_at ? String(tx.updated_at).replace('T', ' ').slice(0, 10) : '—';
                  const reason = (String(tx.notes || '').match(/\[Cancelled: ([^\]]*)\]/) || [])[1] || '—';
                  return `<div class="order-row" data-activity-tx="${tx.id}" title="View details"><span>${esc(when)}</span><strong>${esc(basket)}</strong><span>${esc(tx.customer?.name || '—')}</span><span class="num">${money(tx.total_amount || 0)}</span><span>${esc(reason)}</span></div>`;
              })
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No cancelled orders.</span></div>';
}

export function renderFinanceFiltered(app, data, kpi) {
    app._sumKpi = kpi;
    app.querySelectorAll('[data-sum-kpi]').forEach((el) => {
        el.classList.toggle('is-active', el.dataset.sumKpi === kpi);
    });
    const typeSelect = app.querySelector('[data-sum-type-filter]');
    if (typeSelect) typeSelect.value = kpi === 'sales' ? 'revenue' : kpi === 'expenses' ? 'expenses' : kpi === 'cancelled' ? 'cancelled' : 'all';
    const showCancelled = kpi === 'cancelled';
    const finCard = app.querySelector('[data-sum-finance-card]');
    const canCard = app.querySelector('[data-sum-cancelled-card]');
    if (finCard) finCard.hidden = showCancelled;
    if (canCard) canCard.hidden = !showCancelled;
    const expBox = app.querySelector('[data-sum-show-expenses]');
    if (expBox) expBox.checked = kpi === 'net';
    const body = app.querySelector('[data-admin-finance-rows]');
    const title = app.querySelector('[data-sum-finance-title]');
    if (!body || showCancelled) return;
    let rows = data.finance || [];
    if (kpi === 'sales') rows = rows.filter((f) => f.type === 'income');
    else if (kpi === 'expenses') rows = rows.filter((f) => f.type === 'expense');
    if (title) {
        title.textContent =
            kpi === 'sales' ? 'Income activity' : kpi === 'expenses' ? 'Expense activity' : 'Finance activity';
    }
    body.innerHTML = rows.length
        ? rows
              .map((f) => {
                  const type = f.type === 'expense' ? 'Expense' : 'Income';
                  const who = f.staff?.name || '—';
                  return `<div class="order-row"><strong>${type}</strong><span>${esc(f.description || '—')}</span><span>${esc(who)}</span><span class="num">${money(f.amount)}</span></div>`;
              })
              .join('')
        : '<div class="order-row"><span style="grid-column:1/-1">No rows for this view.</span></div>';
    if (body && (data.metrics?.finance_total || 0) > (data.finance || []).length) {
        body.insertAdjacentHTML('beforeend', `<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Showing latest ${(data.finance || []).length} of ${data.metrics.finance_total} rows in this range.</span></div>`);
    }
}

function renderInventoryAnalytics(app, data) {
    const m = data.metrics || {};
    const set = (sel, val) => {
        const el = app.querySelector(sel);
        if (el) el.textContent = val;
    };
    set('[data-inv-sku]', String(m.inventory_skus ?? (data.inventory || []).length));
    set('[data-inv-low]', String(m.low_stock_count ?? 0));
    set('[data-inv-value]', money(m.inventory_value ?? 0));

    const mode = app._invKpi || 'all';
    const title = app.querySelector('[data-inv-table-title]');
    let items = data.inventory || [];
    if (mode === 'low') {
        items = data.low_stock || items.filter((i) => {
            const thr = i.low_stock_threshold;
            return thr != null && i.quantity_on_hand <= thr;
        });
        if (title) title.textContent = 'Low stock items';
    } else {
        if (title) title.textContent = mode === 'value' ? 'All items (with value)' : 'All items';
    }

    const rows = app.querySelector('[data-admin-inventory-rows]');
    if (rows) {
        rows.innerHTML = items.length
            ? items
                  .map((i) => {
                      const low =
                          i.low_stock_threshold != null &&
                          Number(i.quantity_on_hand) <= Number(i.low_stock_threshold);
                      const qtyLabel =
                          mode === 'value'
                              ? `${i.quantity_on_hand} ${i.unit || ''} · ${money((i.unit_price || 0) * (i.quantity_on_hand || 0))}`
                              : `${i.quantity_on_hand} ${i.unit || ''}`;
                      return `<div class="order-row"><strong>${esc(i.name)}</strong><span>${esc(i.category?.name || '—')}</span><span>${esc(qtyLabel)}</span><em class="status ${low ? 'pending' : 'ready'}">${low ? 'Low stock' : 'In stock'}</em></div>`;
                  })
                  .join('')
            : '<div class="order-row"><span style="grid-column:1/-1">No items.</span></div>';
    }

    renderRankBars(app.querySelector('[data-chart-top-products]'), (data.analytics || {}).popular_products || []);
}

const REPORT_LABELS = {
    sales: 'Sales (revenue) report',
    expenses: 'Expenses report',
    full: 'Full summary',
};

function exportMeta(app, report) {
    const data = app._adminBootstrap || {};
    return [
        ['Report', REPORT_LABELS[report]],
        ['Exported by', `${app.dataset.userName || ''} <${app.dataset.userEmail || ''}>`],
        ['Exported at', new Date().toLocaleString('en-PH')],
        ['Date range', `${data.range?.from || ''} to ${data.range?.to || ''}`],
        [],
    ];
}

function buildSalesReport(app) {
    const data = app._adminBootstrap || {};
    const m = data.metrics || {};
    const from = data.range?.from || '';
    const to = data.range?.to || '';
    const paid = (data.recent_orders || []).filter((o) => {
        const day = String(o.created_at || '').slice(0, 10);
        return o.payment_status === 'paid' && day >= from && day <= to;
    });
    const rows = [
        ...exportMeta(app, 'sales'),
        ['Total sales', m.period_sales ?? 0],
        ['Paid orders', m.paid_orders ?? 0],
        [],
        H(['Order', 'Date', 'Customer', 'Service', 'Amount', 'Status']),
        ...paid.map((tx) => [
            tx.basket_tag?.code || '#' + tx.id,
            String(tx.created_at || '').slice(0, 10),
            tx.customer?.name || '',
            tx.service?.name || tx.transaction_type || '',
            tx.total_amount ?? 0,
            tx.status || '',
        ]),
        [],
        H(['Income records', 'Description', 'Recorded by', 'Amount']),
        ...(data.finance || [])
            .filter((f) => f.type === 'income')
            .map((f) => ['Income', f.description || '', f.staff?.name || '', f.amount ?? 0]),
        [],
        H(['Sales by day', 'Amount']),
        ...((data.analytics || {}).sales_by_day || []).map((d) => [d.date, d.amount]),
        [],
        H(['Popular products', 'Qty sold', 'Revenue']),
        ...((data.analytics || {}).popular_products || []).map((p) => [p.name, p.qty_sold, p.revenue]),
    ];
    return { rows, name: `ssk-sales-report-${from}_to_${to}` };
}

function buildExpensesReport(app) {
    const data = app._adminBootstrap || {};
    const m = data.metrics || {};
    const rows = [
        ...exportMeta(app, 'expenses'),
        ['Total expenses', m.period_expenses ?? 0],
        [],
        H(['Expense records', 'Description', 'Recorded by', 'Amount']),
        ...(data.finance || [])
            .filter((f) => f.type === 'expense')
            .map((f) => ['Expense', f.description || '', f.staff?.name || '', f.amount ?? 0]),
        [],
        H(['Expenses by day', 'Amount']),
        ...((data.analytics || {}).expenses_by_day || []).map((d) => [d.date, d.amount]),
    ];
    return { rows, name: `ssk-expenses-report-${data.range?.from || ''}_to_${data.range?.to || ''}` };
}

/** PDF without a library: render the report in a hidden frame and open the print dialog (Save as PDF). */
function printReportPdf(title, name, rows) {
    const body = rows
        .map((r, i) => {
            if (!r.length) return '<tr class="gap"><td colspan="9"></td></tr>';
            const cells = r.map((c) => `<td>${esc(c ?? '')}</td>`).join('');
            const cls = r.isHeader ? 'head' : i < 4 && r.length === 2 ? 'meta' : '';
            return `<tr class="${cls}">${cells}</tr>`;
        })
        .join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>${esc(name)}</title><style>
        @page { size: A4; margin: 14mm; }
        body { font: 12px/1.4 Arial, sans-serif; color: #143529; }
        h1 { font-size: 20px; margin: 0 0 4px; } .brand { color: #6b7d74; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; border-bottom: 1px solid #e3e8e4; vertical-align: top; }
        tr.head td { background: #e8f1ec; font-weight: 700; border-top: 1px solid #b9cfc4; }
        tr.meta td:first-child { font-weight: 700; width: 120px; }
        tr.gap td { border: 0; padding: 6px; }
    </style></head><body><h1>${esc(title)}</h1><div class="brand">SSK Laba Dami · Laundry Hub</div><table>${body}</table></body></html>`;

    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    document.body.appendChild(frame);
    frame.srcdoc = html;
    frame.onload = () => {
        frame.contentWindow.focus();
        frame.contentWindow.print();
        setTimeout(() => frame.remove(), 60000);
    };
}

function deliverReport(app, report, format) {
    const built =
        report === 'sales'
            ? buildSalesReport(app)
            : report === 'expenses'
              ? buildExpensesReport(app)
              : buildFullReport(app, exportMeta(app, 'full'));
    if (format === 'pdf') printReportPdf(REPORT_LABELS[report], built.name, built.rows);
    else downloadCsv(`${built.name}.csv`, built.rows);
}

function openExportModal(app, preset) {
    const data = app._adminBootstrap || {};
    const set = (sel, v) => {
        const el = document.querySelector(sel);
        if (el) el.textContent = v;
    };
    set('[data-export-who]', `${app.dataset.userName || 'Admin'} (${app.dataset.userEmail || ''})`);
    set('[data-export-range]', `${data.range?.from || '—'} → ${data.range?.to || '—'}`);
    const sel = document.querySelector('[data-export-report]');
    if (sel) sel.value = preset;
    const err = document.querySelector('[data-export-error]');
    if (err) err.hidden = true;
}

function buildFullReport(app, meta = []) {
    const data = app._adminBootstrap || {};
    const m = data.metrics || {};
    const rows = [
        ...meta,
        H(['Section', 'Field', 'Value']),
        ['Metrics', 'Active laundry', m.active_laundry ?? 0],
        ['Metrics', 'Period sales', m.period_sales ?? 0],
        ['Metrics', 'Period expenses', m.period_expenses ?? 0],
        ['Metrics', 'Net', m.net ?? 0],
        ['Metrics', 'Low stock count', m.low_stock_count ?? 0],
        ['Metrics', 'Paid orders', m.paid_orders ?? 0],
        ['Metrics', 'Claimed orders', m.completed_orders ?? 0],
        ['Metrics', 'Machines attention', m.machines_attention ?? 0],
        [],
        H(['Orders', 'Basket', 'Customer', 'Service', 'Amount', 'Status', 'Payment']),
        ...(data.recent_orders || []).map((tx) => [
            'Order',
            tx.basket_tag?.code || '#' + tx.id,
            tx.customer?.name || '',
            tx.service?.name || tx.transaction_type || '',
            tx.total_amount ?? 0,
            tx.status || '',
            tx.payment_status || '',
        ]),
        [],
        H(['Low stock', 'Item', 'On hand', 'Threshold']),
        ...(data.low_stock || []).map((i) => [
            'Stock',
            i.name,
            i.quantity_on_hand,
            i.low_stock_threshold,
        ]),
        [],
        H(['Machines', 'Name', 'Type', 'Status']),
        ...(data.machines || []).map((x) => ['Machine', x.name, x.type, x.status]),
        [],
        H(['Popular products', 'Name', 'Qty', 'Revenue']),
        ...((data.analytics || {}).popular_products || []).map((p) => [
            'Product',
            p.name,
            p.qty_sold,
            p.revenue,
        ]),
        [],
        H(['Sales by day', 'Date', 'Amount']),
        ...((data.analytics || {}).sales_by_day || []).map((d) => ['Sales', d.date, d.amount]),
    ];
    const from = data.range?.from || 'range';
    const to = data.range?.to || '';
    return { rows, name: `ssk-full-summary-${from}_to_${to}` };
}

export function loadAdminBootstrap(app) {
    const q = rangeQuery(app);
    return api('/ajax/admin/bootstrap' + q)
        .then((data) => {
            app._adminBootstrap = data;
            if (data.range) {
                setDefaultDates(app, data.range.from, data.range.to);
            }
            if (!app._dashKpi) app._dashKpi = 'active';
            if (!app._sumKpi) app._sumKpi = 'net';
            if (!app._invKpi) app._invKpi = 'all';
            renderDashboard(app, data);
            renderSummaryAnalytics(app, data);
            renderInventoryAnalytics(app, data);
            renderUsers(app, data);

            // Receiving table
            const restocks = data.restocks || [];
            const rows = app.querySelector('[data-admin-restock-rows]');
            if (rows) {
                rows.innerHTML = restocks.length
                    ? restocks
                          .map((r) => {
                              const date = r.restocked_at ? String(r.restocked_at).slice(0, 10) : '—';
                              const inv = r.invoice_number || '—';
                              const item = r.item?.name || '—';
                              const supplier = r.supplier || '—';
                              const invQty = r.quantity_invoiced ?? '—';
                              const qty = r.quantity_received ?? '—';
                              return `<div class="order-row"><strong>${date}</strong><span>${esc(inv)}</span><span>${esc(item)}</span><span>${esc(supplier)}</span><span class="num">${invQty}</span><span class="num">${qty}</span></div>`;
                          })
                          .join('')
                    : '<div class="order-row"><span style="grid-column:1/-1">No stock receipts yet. Click Record receipt to confirm deliveries from an invoice.</span></div>';
            }
            const countEl = app.querySelector('[data-admin-restock-count]');
            if (countEl) countEl.textContent = String(data.metrics?.receipts_count ?? restocks.length);
            if (restocks[0]) {
                const last = app.querySelector('[data-admin-last-restock]');
                const sub = app.querySelector('[data-admin-last-restock-sub]');
                if (last) last.textContent = restocks[0].item?.name || '—';
                if (sub) {
                    sub.textContent =
                        (restocks[0].supplier || 'No supplier') +
                        ' · +' +
                        (restocks[0].quantity_received || 0);
                }
            }
            const spendEl = app.querySelector('[data-admin-month-spend]');
            if (spendEl && data.metrics) {
                spendEl.textContent = money(data.metrics.receipts_spend ?? 0);
            }
            const poSelect = document.querySelector('[data-po-item]');
            if (poSelect && (data.inventory || []).length) {
                poSelect.innerHTML = data.inventory
                    .map(
                        (i) =>
                            `<option value="${esc(i.id)}">${esc(i.name)} (${i.quantity_on_hand} ${esc(i.unit)})</option>`
                    )
                    .join('');
            }
        })
        .catch((err) => {
            console.warn('SSK admin bootstrap:', err.message);
            const empty = (sel, msg) => {
                const el = app.querySelector(sel);
                if (el) el.innerHTML = `<div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">${esc(msg)}</span></div>`;
            };
            empty('[data-admin-inventory-rows]', 'Could not load inventory. ' + (err.message || 'Try refresh.'));
            empty('[data-admin-restock-rows]', 'Could not load receipts. ' + (err.message || 'Try refresh.'));
            empty('[data-admin-user-rows]', 'Could not load users. ' + (err.message || 'Try refresh.'));
            empty('[data-dash-order-rows]', 'Could not load orders.');
            empty('[data-admin-finance-rows]', 'Could not load finance.');
            app._adminBootstrap = app._adminBootstrap || { metrics: {}, analytics: {}, inventory: [], restocks: [], users: [], recent_orders: [], finance: [], low_stock: [], machines: [] };
        });
}

export function handleAdminClick(t, e, ctx) {
    const { showModal, hideModal, openConfirm, openNotice, app } = ctx;

    const kpiCard = t.closest('[data-kpi]');
    if (kpiCard && app.contains(kpiCard)) {
        e.preventDefault();
        e.stopPropagation();
        const kpi = kpiCard.dataset.kpi;
        app._dashKpi = kpi;
        app.querySelectorAll('[data-kpi]').forEach((el) => {
            el.classList.toggle('is-active', el === kpiCard);
        });
        if (app._adminBootstrap) {
            renderDashboard(app, app._adminBootstrap);
            showDashKpiModal(app, kpi);
        }
        return true;
    }

    const sumKpi = t.closest('[data-sum-kpi]');
    if (sumKpi) {
        e.preventDefault();
        e.stopPropagation();
        app._sumKpi = sumKpi.dataset.sumKpi;
        if (app._adminBootstrap) {
            renderFinanceFiltered(app, app._adminBootstrap, app._sumKpi);
            showSumKpiModal(app, app._sumKpi);
        }
        return true;
    }

    if (t.closest('[data-close-kpi-detail]')) {
        e.preventDefault();
        closeKpiDetailModal();
        return true;
    }

    const invKpi = t.closest('[data-inv-kpi]');
    if (invKpi) {
        e.preventDefault();
        e.stopPropagation();
        app._invKpi = invKpi.dataset.invKpi;
        app.querySelectorAll('[data-inv-kpi]').forEach((el) => {
            el.classList.toggle('is-active', el === invKpi);
        });
        if (app._adminBootstrap) {
            renderInventoryAnalytics(app, app._adminBootstrap);
            const data = app._adminBootstrap;
            if (app._invKpi === 'low') {
                showDashKpiModal(app, 'stock');
            } else {
                const items = data.inventory || [];
                const rows = items
                    .map(
                        (i) =>
                            `<div class="activity-row"><strong>${esc(i.name)}</strong><span>${esc(i.category?.name || '—')}</span><span>${i.quantity_on_hand} ${esc(i.unit || '')}</span><span>${app._invKpi === 'value' ? money((i.unit_price || 0) * (i.quantity_on_hand || 0)) : (Number(i.quantity_on_hand) <= Number(i.low_stock_threshold) ? 'Low stock' : 'In stock')}</span></div>`
                    )
                    .join('');
                openKpiDetailModal(
                    app._invKpi === 'value' ? 'Inventory value' : 'All inventory items',
                    `${items.length} SKU(s) · ${money(data.metrics?.inventory_value || 0)} on hand`,
                    rows || '<div class="activity-row"><span style="grid-column:1/-1">No items.</span></div>'
                );
            }
        }
        return true;
    }

    if (t.closest('[data-sum-apply-range]')) {
        e.preventDefault();
        e.stopPropagation();
        const from = app.querySelector('[data-sum-from]')?.value;
        const to = app.querySelector('[data-sum-to]')?.value;
        if (from || to) setDefaultDates(app, from, to);
        const typeSel = app.querySelector('[data-sum-type-filter]')?.value || 'all';
        if (typeSel === 'revenue') app._sumKpi = 'sales';
        else if (typeSel === 'expenses') app._sumKpi = 'expenses';
        else if (typeSel === 'cancelled') app._sumKpi = 'cancelled';
        else app._sumKpi = 'net';
        loadAdminBootstrap(app).then(() => {
            if (app._adminBootstrap) renderFinanceFiltered(app, app._adminBootstrap, app._sumKpi);
        });
        return true;
    }
    if (t.closest('[data-sum-export]')) {
        e.preventDefault();
        e.stopPropagation();
        openExportModal(app, app._sumKpi === 'expenses' ? 'expenses' : app._sumKpi === 'sales' ? 'sales' : 'full');
        showModal('[data-export-modal]');
        return true;
    }
    if (t.closest('[data-close-export]')) {
        e.preventDefault();
        hideModal('[data-export-modal]');
        return true;
    }
    if (t.closest('[data-confirm-export]')) {
        e.preventDefault();
        const report = document.querySelector('[data-export-report]')?.value || 'full';
        const format = document.querySelector('[data-export-format]')?.value === 'csv' ? 'csv' : 'pdf';
        const data = app._adminBootstrap || {};
        const err = document.querySelector('[data-export-error]');
        api('/ajax/admin/exports', {
            method: 'POST',
            body: JSON.stringify({ report, format, from: data.range?.from || null, to: data.range?.to || null }),
        })
            .then(() => {
                deliverReport(app, report, format);
                hideModal('[data-export-modal]');
                if (format === 'csv') openNotice('Export started', `${REPORT_LABELS[report]} downloaded as CSV.`);
            })
            .catch((ex) => {
                if (err) {
                    err.hidden = false;
                    err.textContent = ex.message;
                }
            });
        return true;
    }
    const dismissBtn = t.closest('[data-dismiss-alert]');
    if (dismissBtn) {
        e.preventDefault();
        dismissBtn.disabled = true;
        api(`/ajax/admin/notifications/${dismissBtn.dataset.dismissAlert}/read`, { method: 'POST', body: '{}' })
            .then(() => loadAdminBootstrap(app))
            .catch((ex) => {
                dismissBtn.disabled = false;
                openNotice('Alert', ex.message);
            });
        return true;
    }

    if (t.matches && t.matches('[data-sum-show-expenses]')) {
        // handled on change below
    }
    const expToggle = t.closest('[data-sum-show-expenses]') || (t.matches?.('[data-sum-show-expenses]') ? t : null);
    if (expToggle && expToggle.matches?.('[data-sum-show-expenses]')) {
        e.stopPropagation();
        const showExp = expToggle.checked;
        app._sumKpi = showExp ? 'net' : 'sales';
        if (app._adminBootstrap) renderFinanceFiltered(app, app._adminBootstrap, app._sumKpi);
        return true;
    }

    if (t.closest('[data-dash-apply-range]')) {
        e.preventDefault();
        const from = app.querySelector('[data-dash-from]')?.value;
        const to = app.querySelector('[data-dash-to]')?.value;
        if (from || to) setDefaultDates(app, from, to);
        loadAdminBootstrap(app);
        return true;
    }
    if (t.closest('[data-dash-export]')) {
        e.preventDefault();
        openExportModal(app, 'full');
        showModal('[data-export-modal]');
        return true;
    }

    if (t.closest('[data-open-activity]')) {
        e.preventDefault();
        if (app._adminBootstrap) renderActivityModalList(app, app._adminBootstrap);
        showModal('[data-activity-modal]');
        return true;
    }
    if (t.closest('[data-close-activity]')) {
        e.preventDefault();
        hideModal('[data-activity-modal]');
        showActivityList();
        return true;
    }
    if (t.closest('[data-activity-back]')) {
        e.preventDefault();
        showActivityList();
        return true;
    }
    const activityRow = t.closest('[data-activity-tx]');
    if (activityRow) {
        e.preventDefault();
        openActivityDetail(app, activityRow.dataset.activityTx);
        return true;
    }
    const dashRow = t.closest('[data-tx-id]');
    if (dashRow && app.querySelector('[data-panel="dashboard"]')?.contains(dashRow)) {
        e.preventDefault();
        openActivityDetail(app, dashRow.dataset.txId);
        return true;
    }


    const cancelOpen = t.closest('[data-cancel-order]');
    if (cancelOpen) {
        e.preventDefault();
        const idEl = document.querySelector('[data-cancel-order-id]');
        const reasonEl = document.querySelector('[data-cancel-order-reason]');
        const errEl = document.querySelector('[data-cancel-order-error]');
        if (idEl) idEl.value = cancelOpen.dataset.cancelOrder;
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
        if (reason.length < 3) {
            if (errEl) {
                errEl.hidden = false;
                errEl.textContent = 'Please give a short reason.';
            }
            return true;
        }
        api(`/ajax/staff/transactions/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) })
            .then((r) => {
                hideModal('[data-cancel-order-modal]');
                openNotice('Order cancelled', r.message || 'The order is now in Cancelled orders.');
                if (app.dataset.role === 'admin') {
                    hideModal('[data-activity-modal]');
                    showActivityList();
                    loadAdminBootstrap(app);
                } else {
                    ctx.loadStaffBootstrap?.();
                }
            })
            .catch((ex) => {
                if (errEl) {
                    errEl.hidden = false;
                    errEl.textContent = ex.message;
                }
            });
        return true;
    }

    const approveBtn = t.closest('[data-approve-user]');
    if (approveBtn) {
        e.preventDefault();
        api(`/ajax/admin/users/${approveBtn.dataset.approveUser}`, {
            method: 'PATCH',
            body: JSON.stringify({ role: 'staff' }),
        })
            .then(() => {
                openNotice('User approved', 'The account can now sign in as staff.');
                loadAdminBootstrap(app);
            })
            .catch((ex) => openNotice('Approve user', ex.message));
        return true;
    }
    const editUserBtn = t.closest('[data-edit-user]');
    if (editUserBtn) {
        e.preventDefault();
        const u = ((app._adminBootstrap || {}).users || []).find((x) => String(x.id) === editUserBtn.dataset.editUser);
        if (!u) return true;
        const setVal = (sel, v) => {
            const el = document.querySelector(sel);
            if (el) el.value = v;
        };
        setVal('[data-edit-user-id]', u.id);
        setVal('[data-edit-user-name]', u.name);
        setVal('[data-edit-user-email]', u.email);
        setVal('[data-edit-user-role]', u.role);
        setVal('[data-edit-user-password]', '');
        const editErr = document.querySelector('[data-edit-user-error]');
        if (editErr) editErr.hidden = true;
        showModal('[data-edit-user-modal]');
        return true;
    }
    if (t.closest('[data-close-edit-user]')) {
        e.preventDefault();
        hideModal('[data-edit-user-modal]');
        return true;
    }
    if (t.closest('[data-confirm-edit-user]')) {
        e.preventDefault();
        const editErr = document.querySelector('[data-edit-user-error]');
        const val = (sel) => document.querySelector(sel)?.value || '';
        const body = {
            name: val('[data-edit-user-name]').trim(),
            email: val('[data-edit-user-email]').trim(),
            role: val('[data-edit-user-role]'),
        };
        const pw = val('[data-edit-user-password]');
        if (pw) body.password = pw;
        api(`/ajax/admin/users/${val('[data-edit-user-id]')}`, { method: 'PATCH', body: JSON.stringify(body) })
            .then(() => {
                hideModal('[data-edit-user-modal]');
                openNotice('User updated', body.email);
                loadAdminBootstrap(app);
            })
            .catch((ex) => {
                if (editErr) {
                    editErr.hidden = false;
                    editErr.textContent = ex.message;
                }
            });
        return true;
    }

    if (t.closest('[data-open-add-user]')) {
        e.preventDefault();
        const err = document.querySelector('[data-add-user-error]');
        if (err) err.hidden = true;
        ['[data-new-user-name]', '[data-new-user-email]', '[data-new-user-password]'].forEach((sel) => {
            const el = document.querySelector(sel);
            if (el) el.value = '';
        });
        showModal('[data-add-user-modal]');
        return true;
    }
    if (t.closest('[data-close-add-user]')) {
        e.preventDefault();
        hideModal('[data-add-user-modal]');
        return true;
    }
    if (t.closest('[data-confirm-add-user]')) {
        e.preventDefault();
        const name = (document.querySelector('[data-new-user-name]')?.value || '').trim();
        const email = (document.querySelector('[data-new-user-email]')?.value || '').trim();
        const password = document.querySelector('[data-new-user-password]')?.value || '';
        const role = document.querySelector('[data-new-user-role]')?.value || 'staff';
        const err = document.querySelector('[data-add-user-error]');
        if (!name || !email || password.length < 8) {
            if (err) {
                err.hidden = false;
                err.textContent = 'Name, email, and password (min 8) are required.';
            }
            return true;
        }
        api('/ajax/admin/users', {
            method: 'POST',
            body: JSON.stringify({ name, email, password, role }),
        })
            .then((r) => {
                hideModal('[data-add-user-modal]');
                openNotice('User created', r.user?.email || email);
                loadAdminBootstrap(app);
            })
            .catch((ex) => {
                if (err) {
                    err.hidden = false;
                    err.textContent = ex.message;
                }
            });
        return true;
    }

    if (t.closest('[data-open-add-item]')) {
        e.preventDefault();
        const err = document.querySelector('[data-add-item-error]');
        if (err) err.hidden = true;
        showModal('[data-add-item-modal]');
        return true;
    }
    if (t.closest('[data-close-add-item]')) {
        e.preventDefault();
        hideModal('[data-add-item-modal]');
        return true;
    }
    if (t.closest('[data-confirm-add-item]')) {
        e.preventDefault();
        const name = (document.querySelector('[data-new-item-name]')?.value || '').trim();
        const categoryId = Number(document.querySelector('[data-new-item-category]')?.value || 0);
        const unit = (document.querySelector('[data-new-item-unit]')?.value || 'pc').trim();
        const price = Number(document.querySelector('[data-new-item-price]')?.value || 0);
        const qty = Number(document.querySelector('[data-new-item-qty]')?.value || 0);
        const thr = Number(document.querySelector('[data-new-item-threshold]')?.value || 0);
        const err = document.querySelector('[data-add-item-error]');
        if (!name || !categoryId) {
            if (err) {
                err.hidden = false;
                err.textContent = 'Name and category are required.';
            }
            return true;
        }
        api('/ajax/admin/inventory', {
            method: 'POST',
            body: JSON.stringify({
                name,
                inventory_category_id: categoryId,
                unit,
                unit_price: price,
                quantity_on_hand: qty,
                low_stock_threshold: thr,
            }),
        })
            .then(() => {
                hideModal('[data-add-item-modal]');
                openNotice('Item added', name + ' is now in inventory.');
                loadAdminBootstrap(app);
            })
            .catch((ex) => {
                if (err) {
                    err.hidden = false;
                    err.textContent = ex.message;
                }
            });
        return true;
    }

    // Stock receiving (procurement)
    if (t.closest('[data-open-procurement]')) {
        e.preventDefault();
        const err = document.querySelector('[data-po-error]');
        if (err) err.hidden = true;
        const today = new Date().toISOString().slice(0, 10);
        const set = (sel, val) => {
            const el = document.querySelector(sel);
            if (el) el.value = val;
        };
        set('[data-po-invoice]', '');
        set('[data-po-invoice-date]', today);
        set('[data-po-supplier]', '');
        set('[data-po-qty-invoiced]', '1');
        set('[data-po-qty]', '1');
        set('[data-po-cost]', '');
        set('[data-po-notes]', '');
        showModal('[data-procurement-modal]');
        return true;
    }
    if (t.closest('[data-close-procurement]')) {
        e.preventDefault();
        hideModal('[data-procurement-modal]');
        return true;
    }
    if (t.closest('[data-confirm-procurement]')) {
        e.preventDefault();
        const itemId = Number(document.querySelector('[data-po-item]')?.value || 0);
        const qty = Number(document.querySelector('[data-po-qty]')?.value || 0);
        const qtyInvoiced = Number(document.querySelector('[data-po-qty-invoiced]')?.value || 0);
        const supplier = (document.querySelector('[data-po-supplier]')?.value || '').trim();
        const invoice = (document.querySelector('[data-po-invoice]')?.value || '').trim();
        const invoiceDate = document.querySelector('[data-po-invoice-date]')?.value || null;
        const notes = (document.querySelector('[data-po-notes]')?.value || '').trim();
        const costRaw = document.querySelector('[data-po-cost]')?.value;
        const cost = costRaw === '' || costRaw == null ? null : Number(costRaw);
        const err = document.querySelector('[data-po-error]');
        if (!itemId || qty < 1) {
            if (err) {
                err.hidden = false;
                err.textContent = 'Select an item and confirm quantity received (min 1).';
            }
            return true;
        }
        const mismatch =
            qtyInvoiced > 0 && qty !== qtyInvoiced
                ? ` Invoice shows ${qtyInvoiced}; you confirmed ${qty}.`
                : '';
        openConfirm(
            'Confirm stock receipt?',
            `Update inventory with ${qty} received${supplier ? ' from ' + supplier : ''}.${invoice ? ' Invoice ' + invoice + '.' : ''}${mismatch} This cannot be undone from here.`,
            () => {
                api('/ajax/admin/procurement', {
                    method: 'POST',
                    body: JSON.stringify({
                        inventory_item_id: itemId,
                        quantity_received: qty,
                        quantity_invoiced: qtyInvoiced || qty,
                        supplier: supplier || null,
                        invoice_number: invoice || null,
                        invoice_date: invoiceDate,
                        restocked_at: invoiceDate,
                        cost: cost,
                        notes: notes || null,
                    }),
                })
                    .then((r) => {
                        hideModal('[data-procurement-modal]');
                        openNotice(
                            'Receipt recorded',
                            r.message || 'Inventory stock updated from this invoice.'
                        );
                        loadAdminBootstrap(app);
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
