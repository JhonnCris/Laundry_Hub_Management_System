<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"><head>@include('partials.head', ['title' => 'SSK Laba Dami | Staff'])</head>
<body class="staff-body"><main class="staff-app" data-staff-app data-shop-name="{{ config('shop.name') }}" data-shop-tagline="{{ config('shop.tagline') }}" data-shop-address="{{ config('shop.address') }}" data-claim-days="{{ config('shop.claim_days') }}">
<aside class="staff-sidebar">
<a class="brand" href="#"><img class="brand-logo" src="{{ asset('images/ssk-laba-dami-logo.jpg') }}" alt="SSK Laba Dami Laundry Hub logo" width="48" height="48"><span class="brand-text"><strong>SSK Laba Dami</strong><small>Laundry Hub</small></span></a>
<nav class="staff-nav">
<p class="nav-group-label">Staff operations</p>
<button class="is-active" data-screen="attendance"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>Attendance Tracking</span></button>
<button data-screen="customers" data-needs-duty><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 21c.5-4 3-6 7-6s6.5 2 7 6"/></svg><span>Manage Customer</span></button>
<button data-screen="transactions" data-needs-duty><svg viewBox="0 0 24 24"><path d="M3 4h18v16H3zM3 9h18M8 9v11"/><path d="M12 14h5M12 18h4"/></svg><span>Transactions</span></button>
<button data-screen="queue"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 7h8M8 12h8M8 17h5"/></svg><span>Active Laundry</span><b data-nav-badge="queue" hidden>0</b></button>
<button data-screen="sales"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h4M8 16h8"/><path d="M16 12v4"/></svg><span>Sales Record</span></button>
<button class="stock-group-toggle" type="button" data-stock-toggle aria-expanded="true" aria-controls="stock-subnav"><svg viewBox="0 0 24 24"><path d="M4 7l8-4 8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10"/></svg><span>Stocks</span><svg class="stock-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></button>
<div class="stock-subnav" id="stock-subnav" data-stock-subnav>
<button data-screen="inventory"><span>Inventory</span><b data-nav-badge="inventory" hidden>0</b></button>
<button data-screen="archived"><span>Archived</span><b data-nav-badge="archived" hidden>0</b></button>
</div>
<button data-screen="machines"><svg viewBox="0 0 24 24"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="4"/></svg><span>Manage Machines</span></button>
</nav>
<button class="staff-user" data-profile type="button"><span>{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>Staff account · Manage</small></div><i>⌄</i></button>
</aside>
<section class="staff-workspace"><header class="mobile-staff-header"><button data-sidebar-toggle type="button">☰</button><strong>SSK Laba Dami</strong></header>
<section class="prototype-panel" data-panel="transactions"><div class="page-intro"><div><p class="eyebrow">New order</p><h1>New Transaction</h1><p>Record every garment clearly before service begins.</p></div></div><div class="transaction-layout"><div class="transaction-main"><section class="staff-card customer-card">
<div class="customer-context" data-customer-context>
    <div class="customer-context-empty" data-customer-empty>
        <p><strong>No customer selected.</strong> Open <button type="button" class="text-button" data-screen="customers">Manage Customer</button> and click <em>Do Laundry</em>, or add a new customer first.</p>
    </div>
    <div class="customer-context-selected" data-customer-selected hidden>
        <div>
            <p class="eyebrow">Transaction for</p>
            <strong data-tx-customer-name>—</strong>
            <span data-tx-customer-phone class="customer-context-phone"></span>
        </div>
        <button type="button" class="text-button" data-change-customer>Change customer</button>
    </div>
</div>
<div class="section-heading"><h2>Load Details</h2><div class="service-toggle"><button class="is-selected" data-service="drop_off" type="button">Drop Off</button><button data-service="self_service" type="button">Self Service</button></div></div>

<div data-workflow="drop_off">
<div class="wide-field">
    <span>Basket Tag <small>For drop-off</small></span>
    <div class="tag-field">
        <input data-basket-tag value="" placeholder="Select basket" readonly>
        <button type="button" data-reassign-basket>Reassign</button>
    </div>
    <div class="basket-picker" data-basket-picker hidden>
        <p class="basket-picker-title">Available baskets</p>
        <ul class="basket-picker-list" data-basket-list>
            <li><span style="padding:8px;color:var(--staff-muted);font-size:13px">Loading available baskets…</span></li>
        </ul>
        <div class="basket-picker-actions">
            <button type="button" class="text-button" data-basket-cancel>Cancel</button>
            <button type="button" class="save-button basket-save" data-basket-confirm disabled>Save basket</button>
        </div>
    </div>
</div>
<div class="garment-heading"><div><strong>Garment separation & count</strong><small>Count each type separately for transparent recording.</small></div><b><span data-summary-garments>0</span> pcs total</b></div>
<div class="garment-grid" data-garment-grid>
    <div class="garment-counter"><span>Shirts</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>0</output><button data-garment-increase type="button">+</button></div></div>
    <div class="garment-counter"><span>Shorts</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>0</output><button data-garment-increase type="button">+</button></div></div>
    <div class="garment-counter"><span>Beddings</span><div><button data-garment-decrease type="button">−</button><output data-garment-count>0</output><button data-garment-increase type="button">+</button></div></div>
    <button class="add-garment" type="button" data-add-garment>Add type <b>+</b></button>
</div>
<div class="form-grid workflow-bottom field-align">
    <label class="field">
        <span class="field-label">Load weight (kg)</span>
        <input type="number" data-load-kg min="0" max="50" step="0.5" placeholder="e.g. 5.0" inputmode="decimal">
        <small class="field-hint">Record actual load weight for the order.</small>
    </label>
    <label class="field">
        <span class="field-label">Service type</span>
        <select data-service-price data-pricing="flat">
            <option value="0" data-none="1">None (snacks &amp; drinks only)</option>
        </select>
        <small class="field-hint field-hint-spacer">&nbsp;</small>
    </label>
    <label class="field">
        <span class="field-label">Detergent / Downy type</span>
        <select data-consumable>
            <option value="0" data-unit-price="0">None</option>
        </select>
    </label>
    <label class="field">
        <span class="field-label">Quantity used</span>
        <div class="qty-field">
            <button type="button" data-consumable-decrease>−</button>
            <input type="number" data-consumable-qty value="0" min="0" max="99" step="1" inputmode="numeric">
            <button type="button" data-consumable-increase>+</button>
        </div>
    </label>
</div>
<label class="pay-later-field"><input type="checkbox" data-pay-later> <span>Customer pays when the laundry is released <small>(no cash taken now)</small></span></label>
</div>
<div data-workflow="self_service" hidden>
<div class="machine-picker" data-machine-picker><p class="dash-empty">Loading machines…</p></div>
<div class="machine-total" data-machine-total hidden></div>
<div class="form-grid workflow-bottom field-align">
    <label class="field">
        <span class="field-label">Load weight (kg) <small>(optional)</small></span>
        <input type="number" data-load-kg min="0" max="50" step="0.5" placeholder="e.g. 6.5" inputmode="decimal">
        <small class="field-hint" data-capacity-hint>Price is per machine, not per kg.</small>
    </label>
    <span></span>
    <label class="field">
        <span class="field-label">Detergent / Downy type</span>
        <select data-consumable>
            <option value="0" data-unit-price="0">None</option>
        </select>
    </label>
    <label class="field">
        <span class="field-label">Quantity used</span>
        <div class="qty-field">
            <button type="button" data-consumable-decrease>−</button>
            <input type="number" data-consumable-qty value="0" min="0" max="99" step="1" inputmode="numeric">
            <button type="button" data-consumable-increase>+</button>
        </div>
    </label>
</div>
<p class="kg-price-preview" data-kg-preview hidden>Machine charge: <strong>—</strong></p>
</div>
</section>
<section class="staff-card add-ons-card"><div class="section-heading"><div><h2>Snacks & Drinks</h2><p>Add items to this order.</p></div><button class="text-button" data-clear-items type="button">Clear all</button></div><div class="add-on-list" data-snack-list><p class="dash-empty" style="padding:8px 0;margin:0">Loading snacks from inventory…</p></div></section></div><aside class="transaction-summary"><section class="summary-card">
<div class="summary-top">
    <p data-summary-title>Summary</p>
    <p class="summary-empty" data-summary-empty>Nothing recorded yet. Add garments or snacks to see a summary.</p>
    <dl data-summary-service-block hidden>
        <div data-row-basket><dt>Basket tag</dt><dd data-summary-tag>—</dd></div>
        <div data-row-service><dt>Service</dt><dd data-summary-service>—</dd></div>
        <div data-row-garments><dt>Garments</dt><dd><span data-summary-garments>0</span> pcs</dd></div>
        <div data-row-kg hidden><dt>Load weight</dt><dd data-summary-kg>—</dd></div>
        <div data-row-duration hidden><dt>Machines</dt><dd data-summary-duration>—</dd></div>
        <div data-row-service-type hidden><dt>Service type</dt><dd data-summary-service-type>—</dd></div>
        <div data-row-consumable hidden><dt>Consumable</dt><dd data-summary-consumable>—</dd></div>
    </dl>
    <dl data-summary-items-block data-summary-items hidden></dl>
</div>
<div class="summary-total" data-summary-total-block hidden><span>Total</span><strong data-total>₱0.00</strong></div>
<button class="save-button" data-save type="button" disabled>Save Transaction</button>
</section>
<section class="notes-card"><label>Notes<textarea placeholder="Special requests, stains, or payment note..."></textarea></label></section>
<p class="save-notice" data-save-notice></p>
</aside></div></section>
<div class="modal-backdrop" data-history-modal hidden>
<section class="profile-modal action-modal history-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-history type="button">×</button>
<h2>Laundry history</h2>
<p class="modal-hint">Every order already marked as claimed, newest first.</p>
<input class="search-input" data-history-search placeholder="Search by customer, order or service">
<div class="history-table">
<div class="history-row history-head"><span>Claimed</span><span>Order</span><span>Customer</span><span>Service</span><span class="num">Total</span></div>
<div data-history-rows><div class="history-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading history…</span></div></div>
</div>
<div class="modal-actions"><button type="button" class="save-button" data-close-history>Close</button></div>
</section>
</div>

<div class="modal-backdrop" data-garment-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-garment-modal type="button">×</button>
<h2>Add garment type</h2>
<p class="modal-hint">Enter a type not listed (e.g. Towels, Jackets, Uniforms).</p>
<label class="modal-field">Garment name<input type="text" data-new-garment-name placeholder="e.g. Towels" maxlength="40"></label>
<label class="modal-field">Starting count<input type="number" data-new-garment-qty value="0" min="0" max="999"></label>
<p class="modal-error" data-garment-modal-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-garment-modal>Cancel</button>
<button type="button" class="save-button" data-confirm-garment>Add garment</button>
</div>
</section>
</div>

<!-- Payment modal: Transaction → Payment → Receipt -->
<div class="modal-backdrop" data-payment-modal hidden>
<section class="profile-modal action-modal payment-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-payment type="button">×</button>
<h2 data-payment-title>Record payment</h2>
<p class="modal-hint" data-payment-hint>Collect cash from the customer, then confirm to issue a receipt.</p>
<div class="payment-summary" data-payment-summary></div>
<label class="modal-field">Amount due<input type="text" data-payment-due readonly></label>
<label class="modal-field" data-payment-cash-field>Cash received<input type="number" data-payment-cash min="0" step="0.01" placeholder="0.00" inputmode="decimal"></label>
<div class="payment-change" data-payment-change-row hidden>
    <span>Change</span>
    <strong data-payment-change>₱0.00</strong>
</div>
<p class="modal-error" data-payment-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-payment>Cancel</button>
<button type="button" class="save-button" data-confirm-payment disabled>Confirm payment</button>
</div>
</section>
</div>

<!-- Receipt modal (after successful payment) -->
<div class="modal-backdrop" data-receipt-modal hidden>
<section class="profile-modal receipt-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-receipt type="button">×</button>
<div data-receipt-body></div>
<div class="modal-actions receipt-actions">
<div class="receipt-actions-row">
<button type="button" class="outline-action" data-send-receipt-email hidden>Email receipt</button>
<button type="button" class="outline-action" data-print-receipt>Print receipt</button>
</div>
<button type="button" class="save-button" data-new-tx-same-customer hidden>New Transaction</button>
</div>
<p class="receipt-action-notice" data-receipt-email-notice aria-live="polite"></p>
</section>
</div>

<!-- Generic action toast / notice modal for secondary buttons -->


<div class="modal-backdrop" data-add-basket-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-add-basket type="button">×</button>
<h2>Add basket</h2>
<p class="modal-hint">Enter a basket number or code (e.g. 021 or #021). It will start as Available.</p>
<label class="modal-field">Basket code
<input type="text" data-new-basket-code placeholder="#021" maxlength="20">
</label>
<p class="modal-error" data-add-basket-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-add-basket>Cancel</button>
<button type="button" class="save-button" data-confirm-add-basket>Save basket</button>
</div>
</section>
</div>

<div class="modal-backdrop" data-archive-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-archive type="button">×</button>
<h2>Archive item</h2>
<p class="modal-hint">Mark stock as expired, spoiled, or damaged.</p>
<label class="modal-field">Item
<select data-archive-item></select>
</label>
<label class="modal-field">Reason
<select data-archive-reason>
<option value="expired">Expired</option>
<option value="spoiled">Spoiled</option>
<option value="damaged">Damaged</option>
</select>
</label>
<label class="modal-field">Quantity to remove
<input type="number" data-archive-qty min="1" step="1" value="1">
</label>
<p class="modal-error" data-archive-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-archive>Cancel</button>
<button type="button" class="save-button" data-confirm-archive>Archive</button>
</div>
</section>
</div>


<div class="modal-backdrop" data-add-customer-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-add-customer type="button">×</button>
<h2>Add New Customer</h2>
<p class="modal-hint">After saving, you will continue to a new transaction for this customer.</p>
<label class="modal-field">First name<input type="text" data-new-customer-first placeholder="e.g. Juan" maxlength="80" autocomplete="given-name"></label>
<label class="modal-field">Middle name <small>(optional)</small><input type="text" data-new-customer-middle placeholder="e.g. Dela" maxlength="80" autocomplete="additional-name"></label>
<label class="modal-field">Last name<input type="text" data-new-customer-last placeholder="e.g. Cruz" maxlength="80" autocomplete="family-name"></label>
<label class="modal-field">Phone / SMS<input type="text" data-new-customer-phone placeholder="09XX XXX XXXX" maxlength="40"></label>
<label class="modal-field">Email <small>(optional)</small><input type="email" data-new-customer-email placeholder="name@email.com"></label>
<label class="modal-field">Address <small>(optional)</small><input type="text" data-new-customer-address placeholder="Street, barangay, city"></label>
<p class="modal-error" data-add-customer-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-add-customer>Cancel</button>
<button type="button" class="save-button" data-confirm-add-customer>Save &amp; start laundry</button>
</div>
</section>
</div>

<div class="modal-backdrop" data-edit-customer-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-edit-customer type="button">×</button>
<h2>Edit contact</h2>
<p class="modal-hint">Name is locked to protect past transaction history. Update phone/SMS or email only.</p>
<label class="modal-field">Name<input type="text" data-edit-customer-name readonly></label>
<label class="modal-field">Phone / SMS<input type="text" data-edit-customer-phone maxlength="40"></label>
<label class="modal-field">Email <small>(optional)</small><input type="email" data-edit-customer-email></label>
<input type="hidden" data-edit-customer-id>
<p class="modal-error" data-edit-customer-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-edit-customer>Cancel</button>
<button type="button" class="save-button" data-confirm-edit-customer>Save changes</button>
</div>
</section>
</div>


<div class="modal-backdrop" data-confirm-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-confirm type="button">×</button>
<h2 data-confirm-title>Confirm</h2>
<p class="modal-hint" data-confirm-body></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-confirm>Cancel</button>
<button type="button" class="save-button" data-confirm-yes>Yes, continue</button>
</div>
</section>
</div>

<div class="modal-backdrop" data-cancel-order-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-cancel-order type="button">×</button>
<h2>Cancel order</h2>
<p class="modal-hint">The order is removed from sales, its stock is returned, and it is kept in Cancelled orders. This cannot be undone.</p>
<input type="hidden" data-cancel-order-id>
<label class="modal-field">Reason<input type="text" data-cancel-order-reason maxlength="200" placeholder="e.g. Customer changed mind"></label>
<p class="modal-error" data-cancel-order-error hidden></p>
<div class="modal-actions">
<button type="button" class="text-button" data-close-cancel-order>Keep order</button>
<button type="button" class="save-button" data-confirm-cancel-order>Cancel order</button>
</div>
</section>
</div>

<div class="modal-backdrop" data-notice-modal hidden>
<section class="profile-modal action-modal" role="dialog" aria-modal="true">
<button class="modal-close" data-close-notice type="button">×</button>
<h2 data-notice-title>Notice</h2>
<p data-notice-body class="modal-hint"></p>
<div class="modal-actions">
<button type="button" class="save-button" data-close-notice>OK</button>
</div>
</section>
</div>
<section class="prototype-panel" data-panel="queue"><div class="page-intro"><div><p class="eyebrow">Laundry workflow</p><h1>Active Laundry</h1><p>Track every current order through release.</p></div><button class="action-btn-secondary" type="button" data-open-history title="Orders already marked claimed">History →</button></div>
<section class="staff-card full-card table-card">
<div class="order-row order-head"><span>Order</span><span>Customer</span><span>Service</span><span>Status</span><span>Action</span></div>
<div data-queue-rows></div>
</section></section>
<section class="prototype-panel" data-panel="sales">
<div class="page-intro"><div><p class="eyebrow">Payments</p><h1>Sales Record</h1><p>Browse transactions and reopen receipts.</p></div></div>
<section class="staff-card full-card table-card">
<div class="sales-filter"><label for="sale-record-filter">Filter</label><select id="sale-record-filter" data-sale-filter><option value="" hidden>All records</option><option value="day" selected>Day</option><option value="week">Week</option><option value="month">Month</option></select><button class="action-btn-secondary" type="button" data-sale-all>Show all</button></div>
<div class="order-row sale-row sale-head"><span>Order</span><span>Date</span><span>Customer</span><span>Status</span><span>Total</span><span>Receipt</span></div>
<div data-sale-rows><div class="order-row"><span>Loading sales records…</span></div></div>
</section></section>
<section class="prototype-panel" data-panel="customers">
<div class="page-intro">
    <div>
        <p class="eyebrow">Customer hub</p>
        <h1>Manage Customer</h1>
        <p>Find a customer, update contact info, or start a laundry order.</p>
    </div>
    <button class="action-btn-primary" type="button" data-open-add-customer>Add New Customer</button>
</div>
<section class="staff-card full-card table-card">
    <input class="search-input" data-customer-search placeholder="Search by name or phone number">
    <div class="order-row order-head">
        <span>Name</span>
        <span>Phone / SMS</span>
        <span>Email</span>
        <span>Orders</span>
        <span>Last visit</span>
        <span>Action</span>
    </div>
    <div data-customer-rows></div>
</section>
</section>
<section class="prototype-panel" data-panel="inventory"><div class="page-intro"><div><p class="eyebrow">Stock overview</p><h1>Inventory Tracking</h1><p>View stock levels and archive damaged, spoiled, or expired items. New stock is added by Admin through Procurement.</p></div>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<button class="action-btn-secondary" type="button" data-open-archive title="Mark stock as expired, spoiled, or damaged">Archive item →</button>
</div></div>
<section class="staff-card full-card table-card"><div class="section-heading" style="padding:16px 20px 0;margin-bottom:0"><h2>Supplies & consumables</h2></div>
<div class="table-filters"><select data-staff-inv-category aria-label="Filter by category"><option value="">All categories</option></select></div>
<div class="order-row order-head inv5"><span>Item</span><span>Category</span><span>Quantity</span><span>Status</span><span>Alert admin</span></div>
<div data-inventory-rows></div>
</section>
<section class="staff-card full-card table-card" style="margin-top:18px">
<div class="section-heading" style="padding:16px 20px 0;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h2 style="margin:0">Laundry baskets</h2>
    <select class="basket-filter" data-basket-filter aria-label="Filter baskets by status"><option value="">All baskets</option><option value="in_use">In use</option><option value="available">Available</option></select>
    <button class="action-btn-primary" type="button" data-open-add-basket title="Register a new basket tag">Add basket →</button>
</div>
<div class="order-row order-head"><span>Basket</span><span>Status</span><span>Assigned to</span><span>Order</span></div>
<div data-basket-rows></div>
</section></section>
<section class="prototype-panel" data-panel="archived">
<div class="page-intro"><div><p class="eyebrow">Stock</p><h1>Archived</h1><p>Stock removed as expired, spoiled, or damaged, with how many were removed.</p></div></div>
<section class="staff-card full-card table-card">
<div class="order-row order-head"><span>Date</span><span>Item</span><span>Category</span><span>Reason</span><span>Quantity removed</span></div>
<div data-archived-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">No archived items.</span></div></div>
</section>
</section>
<section class="prototype-panel" data-panel="machines"><div class="page-intro"><div><p class="eyebrow">Machine monitoring</p><h1>Manage Machines</h1><p>Availability, reservations, and maintenance at a glance.</p></div></div>
<div class="machine-grid" data-machine-grid></div>
</section>
<section class="prototype-panel is-visible" data-panel="attendance">
<div class="page-intro">
    <div>
        <p class="eyebrow">Timekeeping</p>
        <h1>Attendance Tracking</h1>
        <p>Clock in and out for your shift. Today’s record is shown below.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="action-btn-primary" type="button" data-clock-in title="Start your shift">Clock in →</button>
        <button class="action-btn-secondary" type="button" data-clock-out title="End your shift">Clock out →</button>
    </div>
</div>
<section class="staff-card duty-banner is-locked" data-duty-lock>
    <div><strong>Clock in to start your shift</strong><p>Manage Customer and Transactions stay locked until you clock in. After you clock out they lock again.</p></div>
</section>
<section class="staff-card duty-banner is-ready" data-duty-guide hidden>
    <div><strong>You are on duty</strong><p>Next step: open Manage Customer, find or add the customer, then start their laundry or purchase.</p></div>
    <button class="action-btn-primary" type="button" data-guide-customers>Go to Manage Customer →</button>
</section>
<div class="metric-grid">
    <article><span>Status today</span><strong data-att-status>—</strong><small data-att-status-sub>Not clocked in</small></article>
    <article><span>Hours so far</span><strong data-att-hours>—</strong><small>Updates on clock out</small></article>
    <article><span>Records</span><strong data-att-count>0</strong><small>Most recent 10 shown</small></article>
</div>
<section class="staff-card full-card table-card">
    <div class="section-heading" style="padding:16px 20px 0;margin-bottom:0"><h2>Recent attendance</h2></div>
    <div class="order-row order-head"><span>Date</span><span>Clock in</span><span>Clock out</span><span>Status</span></div>
    <div data-attendance-rows></div>
</section>
</section>
</section>
<div class="modal-backdrop" data-modal hidden><section class="profile-modal" role="dialog" aria-modal="true"><button class="modal-close" data-close-modal type="button">×</button>
<div data-modal-view="menu"><span class="profile-avatar">SS</span><h2>{{ auth()->user()->name }}</h2><p>{{ auth()->user()->email }}</p><div class="settings-options"><button data-show-view="profile" type="button">Edit profile settings <span>›</span></button><button data-show-view="password" type="button">Change password <span>›</span></button><button data-show-view="display" type="button">Display settings <span>›</span></button><button data-show-view="language" type="button">Language <span>›</span></button><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit">Log out</button></form></div></div>
<div data-modal-view="profile" hidden><button class="text-button" data-show-view="menu" type="button">‹ Back</button><h2>Edit profile</h2><livewire:settings.profile-modal-form /></div>
<div data-modal-view="password" hidden><button class="text-button" data-show-view="menu" type="button">‹ Back</button><h2>Change password</h2><livewire:settings.password-modal-form /></div>
<div data-modal-view="display" hidden><button class="text-button" data-show-view="menu" type="button">‹ Back</button><h2>Display settings</h2>
    <div class="appearance-options">
        <div>
            <label class="group-label">Theme</label>
            <div class="option-toggle" data-theme-toggle>
                <button type="button" data-theme-option="light">Light</button>
                <button type="button" data-theme-option="dark">Dark</button>
            </div>
        </div>
        <div>
            <label class="group-label">Text size</label>
            <small style="color:var(--staff-muted);font-size:calc(12px * var(--text-scale))">Enlarge text if it's hard to read.</small>
            <div class="option-toggle" data-text-size-toggle>
                <button type="button" data-text-size-option="normal">Normal</button>
                <button type="button" data-text-size-option="large">Large</button>
                <button type="button" data-text-size-option="xlarge">Extra large</button>
            </div>
        </div>
    </div>
</div>
<div data-modal-view="language" hidden><button class="text-button" data-show-view="menu" type="button">‹ Back</button><h2>Language</h2>
    <small style="color:var(--staff-muted);font-size:calc(12px * var(--text-scale))">Choose the language used by the system text.</small>
    <div class="option-toggle" data-locale-toggle>
        <button type="button" data-locale-option="en">English</button>
        <button type="button" data-locale-option="fil">Filipino (Tagalog)</button>
    </div>
</div>
</section></div>
@include('partials.help-guide')
</main></body></html>
