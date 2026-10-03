import { api, esc, money } from './core.js';

const saleDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
};

export function loadSales(app) {
    const rows = app.querySelector('[data-sale-rows]');
    if (!rows) return Promise.resolve();
    rows.innerHTML = '<div class="order-row"><span>Loading sale records…</span></div>';
    const selectedDate = app.querySelector('[data-sale-date]')?.value || '';

    return api('/api/staff/history')
        .then((result) => {
            const byId = new Map();
            [...(app._bootstrap?.active_laundry || []), ...(result.orders || [])].forEach((transaction) => {
                byId.set(Number(transaction.id), transaction);
            });
            const transactions = [...byId.values()]
                .filter((transaction) => !selectedDate || localDateKey(transaction.created_at) === selectedDate)
                .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            app._saleTransactions = transactions;
            rows.innerHTML = transactions.length
                ? transactions.map((transaction) => {
                      const id = Number(transaction.id);
                      const customer = transaction.customer?.name || '—';
                      return `<div class="order-row sale-row" data-sale-row>
                          <strong>#${id}</strong><span>${esc(saleDate(transaction.created_at))}</span><span>${esc(customer)}</span>
                          <span><em class="status ${transaction.payment_status === 'paid' ? 'ready' : 'pending'}">${esc(transaction.payment_status || transaction.status)}</em></span>
                          <span>${money(transaction.total_amount)}</span>
                          <button type="button" class="action-btn-secondary" data-view-sale="${id}">View receipt</button>
                      </div>`;
                  }).join('')
                : '<div class="order-row"><span style="grid-column:1/-1">No transactions found for this day.</span></div>';
        })
        .catch((error) => {
            rows.innerHTML = `<div class="order-row"><span style="grid-column:1/-1">${esc(error.message || 'Could not load sale records.')}</span></div>`;
        });
}

function localDateKey(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function receiptHtml(transaction) {
    const escValue = esc;
    const service = transaction.service?.name || (transaction.transaction_type === 'self_service' ? 'Self Service' : 'Drop off');
    const customer = transaction.customer?.name || '—';
    const staff = transaction.handled_by?.name || 'Staff';
    const createdAt = new Date(transaction.created_at || Date.now());
    const readyAt = new Date(createdAt);
    readyAt.setDate(readyAt.getDate() + 1);
    readyAt.setHours(17, 0, 0, 0);
    const lines = [];
    const line = (label, amount) => lines.push(`<tr><td>${escValue(label)}</td><td>${money(amount)}</td></tr>`);
    const detail = (label) => lines.push(`<tr class="receipt-detail"><td colspan="2">${escValue(label)}</td></tr>`);

    if (transaction.service || transaction.load_weight_kg || transaction.transaction_type === 'drop_off') {
        const hasItemDetails = Array.isArray(transaction.inventory_items) && Array.isArray(transaction.garment_types);
        const detergentTotal = Number(transaction.detergent?.unit_price || 0) * Number(transaction.detergent_quantity || 0);
        const itemTotal = (transaction.inventory_items || []).reduce((sum, item) => sum + Number(item.pivot?.line_total || 0), 0);
        const serviceTotal = hasItemDetails
            ? Math.max(0, Number(transaction.subtotal || transaction.total_amount) - detergentTotal - itemTotal)
            : null;
        line(`${transaction.transaction_type === 'self_service' ? 'Self service' : 'Drop off'} · ${service}`, serviceTotal);
        if (transaction.load_weight_kg) detail(`${transaction.load_weight_kg} kg${transaction.transaction_type === 'drop_off' ? ` · ${(transaction.garment_types || []).reduce((sum, garment) => sum + Number(garment.pivot?.quantity || 0), 0)} garments` : ''}`);
        if (transaction.basket_tag?.code) detail(`Basket ${transaction.basket_tag.code}`);
    }
    if (transaction.detergent && Number(transaction.detergent_quantity) > 0) {
        line(transaction.detergent.name, Number(transaction.detergent.unit_price || 0) * Number(transaction.detergent_quantity));
        detail(`${transaction.detergent_quantity} × ${money(transaction.detergent.unit_price)}`);
    }
    (transaction.inventory_items || []).forEach((item) => {
        line(item.name, item.pivot?.line_total ?? Number(item.pivot?.unit_price || 0) * Number(item.pivot?.quantity || 0));
        detail(`${item.pivot?.quantity || 0} × ${money(item.pivot?.unit_price)}`);
    });
    (transaction.garment_types || []).forEach((garment) => detail(`${garment.name}: ${garment.pivot?.quantity || 0}`));

    return `<div class="receipt">
        <div class="receipt-brand"><strong>Fresh Wash Laundry</strong><small>Matina, Davao City</small></div><hr>
        <div class="receipt-order"><strong>Order #${escValue(transaction.id)}</strong><span>${escValue(saleDate(transaction.created_at))}</span></div>
        <div class="receipt-meta-grid"><span>Customer</span><strong>${escValue(customer)}</strong><span>Cashier</span><strong>${escValue(staff)}</strong></div><hr>
        <table class="receipt-lines">${lines.join('')}</table><hr>
        <table class="receipt-totals"><tr class="receipt-grand"><td>Total</td><td>${money(transaction.total_amount)}</td></tr>
        <tr><td>Cash</td><td>${money(transaction.cash_tendered)}</td></tr><tr><td>Change</td><td>${money(transaction.change_given)}</td></tr></table>
        <div class="receipt-paid">${escValue(transaction.payment_status || 'PAID').toUpperCase()}</div>
        ${transaction.transaction_type && !['claimed', 'cancelled'].includes(transaction.status) ? `<div class="receipt-ready"><strong>Ready: ${escValue(readyAt.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }))}</strong><span>Please bring this receipt at pickup and claim within 7 days.</span></div>` : ''}
        <p class="receipt-footer">Thank you for choosing us</p>
    </div>`;
}

export function handleSalesClick(t, e, app, showModal) {
    if (t.closest('[data-sale-all]')) {
        e.preventDefault();
        const date = app.querySelector('[data-sale-date]');
        if (date) date.value = '';
        loadSales(app);
        return true;
    }

    const viewButton = t.closest('[data-view-sale]');
    if (viewButton) {
        e.preventDefault();
        const transaction = (app._saleTransactions || []).find((sale) => Number(sale.id) === Number(viewButton.dataset.viewSale));
        if (!transaction) return true;
        app._receiptTransactionId = transaction.id;
        app._receiptTotal = transaction.total_amount;
        app._receiptCustomerPhone = transaction.customer?.contact_number || '';
        app._selectedCustomer = transaction.customer ? {
            id: transaction.customer_id,
            name: transaction.customer.name,
            contact_number: transaction.customer.contact_number,
        } : null;
        if (app._selectedCustomer) {
            try {
                sessionStorage.setItem('ssk_selected_customer', JSON.stringify(app._selectedCustomer));
            } catch (error) {}
        }
        const body = app.querySelector('[data-receipt-body]');
        const smsNotice = app.querySelector('[data-receipt-sms-notice]');
        if (body) body.innerHTML = receiptHtml(transaction);
        if (smsNotice) smsNotice.textContent = '';
        const smsButton = app.querySelector('[data-send-receipt-sms]');
        if (smsButton) smsButton.hidden = !transaction.customer?.contact_number;
        showModal('[data-receipt-modal]');
        return true;
    }

    return false;
}

export function handleSalesInput(t, app) {
    if (!t.matches('[data-sale-date]')) return false;
    loadSales(app);
    return true;
}
