<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => 'SSK Laba Dami | Admin'])</head>
<body class="staff-body">
<main class="staff-app" data-staff-app data-role="admin">

<aside class="staff-sidebar">
    <a class="brand" href="{{ route('admin.dashboard') }}">
        <img class="brand-logo" src="{{ asset('images/ssk-laba-dami-logo.jpg') }}" alt="SSK Laba Dami Laundry Hub logo" width="48" height="48">
        <span class="brand-text"><strong>SSK Laba Dami</strong><small>Admin console</small></span>
    </a>
    <nav class="staff-nav">
        <p class="nav-group-label">Admin</p>
        <button class="is-active" data-screen="dashboard" type="button">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <span>Dashboard</span>
        </button>
        <button data-screen="summary" type="button">
            <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
            <span>Sales & Summary</span>
        </button>
        <button data-screen="manage-inventory" type="button">
            <svg viewBox="0 0 24 24"><path d="M4 7l8-4 8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10"/></svg>
            <span>Manage Inventory</span>
        </button>
        <button data-screen="procurement" type="button">
            <svg viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12zM6 6L5 3H2M9 20a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
            <span>Stock Receiving</span>
        </button>
        <button data-screen="manage-users" type="button">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c.5-3.5 2.5-5.5 6-5.5s5.5 2 6 5.5M16 11a3 3 0 100-6M19 20c-.3-2.5-1.5-4-3.5-4.8"/></svg>
            <span>Manage Users</span>
        </button>
    </nav>
    <button class="staff-user" data-profile type="button">
        <span>{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
        <div>
            <strong>{{ auth()->user()->name }}</strong>
            <small>Admin account · Manage</small>
        </div>
        <i>⌄</i>
    </button>
</aside>

<section class="staff-workspace">
    <header class="mobile-staff-header">
        <button data-sidebar-toggle type="button">☰</button>
        <strong>SSK Laba Dami · Admin</strong>
    </header>

    {{-- Admin: Dashboard --}}
    <section class="prototype-panel is-visible" data-panel="dashboard">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Overview</p>
                <h1>Dashboard</h1>
                <p>Operations, sales, stock alerts, and machines at a glance.</p>
            </div>
            <div class="dash-toolbar">
                <label class="dash-filter">
                    <span>From</span>
                    <input type="date" data-dash-from aria-label="Filter from date">
                </label>
                <label class="dash-filter">
                    <span>To</span>
                    <input type="date" data-dash-to aria-label="Filter to date">
                </label>
                <button class="action-btn-primary" type="button" data-dash-apply-range>Filter</button>
                <button class="outline-action" type="button" data-dash-export title="Download CSV">Export CSV →</button>
            </div>
        </div>

        <div class="metric-grid dash-kpis">
            <article class="kpi-card is-active" data-kpi="active" title="Click to show active laundry orders">
                <span>Active laundry</span>
                <strong data-kpi-active-value>—</strong>
                <small data-kpi-active-sub>Click to filter activity</small>
            </article>
            <article class="kpi-card" data-kpi="stock" title="Click to show low-stock items">
                <span>Low stock</span>
                <strong data-kpi-stock-value>—</strong>
                <small data-kpi-stock-sub>Action required</small>
            </article>
            <article class="kpi-card" data-kpi="machines" title="Click to show machines needing attention">
                <span>Machines</span>
                <strong data-kpi-machines-value>—</strong>
                <small data-kpi-machines-sub>Maintenance / offline</small>
            </article>
        </div>

        <div class="dash-panels">
            <section class="staff-card full-card">
                <div class="section-heading">
                    <h2 data-dash-activity-title>Order activity</h2>
                    <button class="text-button" type="button" data-open-activity title="View transaction details">Activity</button>
                </div>
                <div class="order-row order-head"><span>Basket</span><span>Customer</span><span>Service</span><span>Amount</span><span>Status</span></div>
                <div data-dash-order-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading orders…</span></div></div>
            </section>

            <section class="staff-card full-card">
                <div class="section-heading">
                    <h2>Alerts</h2>
                    <small data-dash-alert-count style="color:var(--staff-muted)">0 alerts</small>
                </div>
                <div data-dash-alerts><p class="dash-empty">Loading alerts…</p></div>
            </section>
        </div>

        <section class="staff-card full-card dash-busy-card" style="margin-top:18px">
            <div class="section-heading">
                <h2>Busiest hours</h2>
                <small style="color:var(--staff-muted)">Orders by time of day in the selected range</small>
            </div>
            <div class="chart-bars chart-hours chart-hours-wide" data-chart-busy-hours><p class="chart-empty">Loading busiest hours…</p></div>
            <p class="busy-peak-note" data-busy-peak-note></p>
        </section>

        <div class="dash-panels" style="margin-top:18px">
            <section class="staff-card full-card">
                <div class="section-heading">
                    <h2>Sales per day</h2>
                    <small style="color:var(--staff-muted)">₱, selected range</small>
                </div>
                <div class="chart-bars" data-chart-dash-sales><p class="chart-empty">Loading sales…</p></div>
            </section>
            <section class="staff-card full-card">
                <div class="section-heading">
                    <h2>Orders by service</h2>
                    <small style="color:var(--staff-muted)">Share of orders</small>
                </div>
                <div data-dash-service-share><p class="dash-empty">Loading service mix…</p></div>
            </section>
        </div>
    </section>

    {{-- Admin: Sales & Summary --}}
    <section class="prototype-panel" data-panel="summary">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Finance</p>
                <h1>Sales & Summary</h1>
                <p>Revenue, expenses, service mix, and product popularity for the selected range.</p>
            </div>
            <div class="dash-toolbar">
                <label class="dash-filter">
                    <span>From</span>
                    <input type="date" data-sum-from aria-label="Summary from date">
                </label>
                <label class="dash-filter">
                    <span>To</span>
                    <input type="date" data-sum-to aria-label="Summary to date">
                </label>
                <label class="dash-filter">
                    <span>Show</span>
                    <select data-sum-type-filter aria-label="Filter by revenue or expenses">
                        <option value="all">All activity</option>
                        <option value="revenue">Revenue only</option>
                        <option value="expenses">Expenses only</option>
                    </select>
                </label>
                <button class="outline-action" type="button" data-sum-apply-range>Filter</button>
                <button class="action-btn-primary" type="button" data-sum-export>Export CSV →</button>
            </div>
        </div>

        <div class="metric-grid dash-kpis">
            <article class="kpi-card is-active" data-sum-kpi="sales" title="Show income rows">
                <span>Total sales</span>
                <strong data-sum-sales>—</strong>
                <small data-sum-sales-sub>Paid laundry in range</small>
            </article>
            <article class="kpi-card" data-sum-kpi="expenses" title="Show expense rows">
                <span>Total expenses</span>
                <strong data-sum-expenses>—</strong>
                <small data-sum-expenses-sub>Includes stock receipts</small>
            </article>
            <article class="kpi-card" data-sum-kpi="net" title="Show all finance rows">
                <span>Net amount</span>
                <strong data-sum-net>—</strong>
                <small data-sum-net-sub>Sales − expenses</small>
            </article>
        </div>

        <section class="staff-card full-card" style="margin-top:8px">
            <div class="section-heading"><h2>Daily sales</h2><small data-sum-range-label style="color:var(--staff-muted)">—</small></div>
            <div class="chart-bars" data-chart-sales-expenses><p class="chart-empty">Loading daily sales…</p></div>
        </section>

        <div class="dash-panels" style="margin-top:18px">
            <section class="staff-card full-card">
                <div class="section-heading"><h2>Service mix</h2><small style="color:var(--staff-muted)">Share of sales</small></div>
                <div data-sum-service-mix><p class="dash-empty">Loading service mix…</p></div>
            </section>
            <section class="staff-card full-card">
                <div class="section-heading"><h2>Popular products</h2><small style="color:var(--staff-muted)">Snacks, drinks & extras sold</small></div>
                <div class="order-row order-head popular-head"><span>Product</span><span>Qty sold</span><span>Revenue</span></div>
                <div data-popular-product-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading products…</span></div></div>
            </section>
        </div>

        <section class="staff-card full-card table-card" style="margin-top:18px">
            <div class="section-heading" style="padding:16px 20px 0;margin-bottom:0;display:flex;justify-content:space-between;align-items:center">
                <h2 data-sum-finance-title style="margin:0">Finance activity</h2>
                <label class="toggle-inline"><input type="checkbox" data-sum-show-expenses> Show expense rows</label>
            </div>
            <div class="order-row order-head"><span>Type</span><span>Description</span><span>Recorded by</span><span>Amount</span></div>
            <div data-admin-finance-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading finance…</span></div></div>
        </section>
    </section>

    {{-- Admin: Manage Inventory --}}
    <section class="prototype-panel" data-panel="manage-inventory">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Stock control</p>
                <h1>Manage Inventory</h1>
                <p>Stock levels, value, low-stock alerts, and what sells most from the counter.</p>
            </div>
            <button class="action-btn-primary" type="button" data-open-add-item>Add item →</button>
        </div>
        <div class="metric-grid dash-kpis">
            <article class="kpi-card is-active" data-inv-kpi="all" title="Show all items">
                <span>SKUs tracked</span>
                <strong data-inv-sku>—</strong>
                <small>All inventory items</small>
            </article>
            <article class="kpi-card" data-inv-kpi="low" title="Show low stock only">
                <span>Low stock</span>
                <strong data-inv-low>—</strong>
                <small>Below threshold</small>
            </article>
            <article class="kpi-card" data-inv-kpi="value" title="Show all with values">
                <span>On-hand value</span>
                <strong data-inv-value>—</strong>
                <small>Qty × unit price</small>
            </article>
        </div>
        <div class="dash-panels">
            <section class="staff-card full-card table-card">
                <div class="section-heading" style="padding:16px 20px 0;margin-bottom:0"><h2 data-inv-table-title>All items</h2></div>
                <div class="order-row order-head"><span>Item</span><span>Category</span><span>Quantity</span><span>Status</span></div>
                <div data-admin-inventory-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading inventory…</span></div></div>
            </section>
            <section class="staff-card full-card">
                <div class="section-heading"><h2>Top sellers</h2><small style="color:var(--staff-muted)">From paid orders in current range</small></div>
                <div class="chart-bars" data-chart-top-products></div>
                <div data-inv-popular-rows style="margin-top:8px"></div>
            </section>
        </div>
    </section>

    {{-- Admin: Stock Receiving (invoice-based) --}}
    <section class="prototype-panel" data-panel="procurement">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Receiving</p>
                <h1>Stock Receiving</h1>
                <p>Record what was delivered and confirm quantities against the supplier invoice. Stock is updated only when you save a receipt.</p>
            </div>
            <button class="action-btn-primary" type="button" data-open-procurement title="Record goods received from an invoice">Record receipt →</button>
        </div>
        <div class="metric-grid">
            <article><span>Receipts logged</span><strong data-admin-restock-count>0</strong><small>Confirmed deliveries</small></article>
            <article><span>Invoice spend</span><strong data-admin-month-spend>₱0</strong><small>Logged as expenses</small></article>
            <article><span>Last receipt</span><strong data-admin-last-restock>—</strong><small data-admin-last-restock-sub>No records yet</small></article>
        </div>
        <section class="staff-card full-card table-card">
            <div class="order-row order-head"><span>Received</span><span>Invoice #</span><span>Item</span><span>Supplier</span><span>Invoiced</span><span>Received qty</span></div>
            <div data-admin-restock-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading receipts…</span></div></div>
        </section>
    </section>

    {{-- Admin: Manage Users --}}
    <section class="prototype-panel" data-panel="manage-users">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Accounts</p>
                <h1>Manage Users</h1>
                <p>Create and update staff and admin accounts and their roles.</p>
            </div>
            <button class="action-btn-primary" type="button" data-open-add-user>Add user →</button>
        </div>
        <div class="metric-grid">
            <article><span>Total users</span><strong data-users-total>0</strong><small>Accounts</small></article>
            <article><span>Admins</span><strong data-users-admins>0</strong><small>Full access</small></article>
            <article><span>Staff</span><strong data-users-staff>0</strong><small>Operations</small></article>
        </div>
        <section class="staff-card full-card table-card">
            <div class="order-row order-head"><span>Name</span><span>Email</span><span>Role</span><span>Status</span></div>
            <div data-admin-user-rows><div class="order-row"><span style="grid-column:1/-1;color:var(--staff-muted)">Loading users…</span></div></div>
        </section>
    </section>
</section>





<div class="modal-backdrop" data-add-user-modal hidden>
    <section class="profile-modal action-modal admin-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-add-user type="button">×</button>
        <h2>Add user</h2>
        <p class="modal-hint">Create a staff or admin account. They can sign in with this email and password.</p>
        <label class="modal-field">Name<input type="text" data-new-user-name placeholder="Full name"></label>
        <label class="modal-field">Email<input type="email" data-new-user-email placeholder="name@ssklabadami.test"></label>
        <label class="modal-field">Password<input type="password" data-new-user-password placeholder="Min 8 characters"></label>
        <label class="modal-field">Role
            <select data-new-user-role>
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
            </select>
        </label>
        <p class="modal-error" data-add-user-error hidden></p>
        <div class="modal-actions">
            <button type="button" class="text-button" data-close-add-user>Cancel</button>
            <button type="button" class="save-button" data-confirm-add-user>Create user</button>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-add-item-modal hidden>
    <section class="profile-modal action-modal admin-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-add-item type="button">×</button>
        <h2>Add inventory item</h2>
        <p class="modal-hint">New items appear in staff inventory and stock receiving.</p>
        <label class="modal-field">Name<input type="text" data-new-item-name placeholder="e.g. Downy Sachet"></label>
        <label class="modal-field">Category
            <select data-new-item-category></select>
        </label>
        <label class="modal-field">Unit<input type="text" data-new-item-unit placeholder="sachet, bottle…" value="pc"></label>
        <label class="modal-field">Unit price (₱)<input type="number" data-new-item-price min="0" step="0.01" value="0"></label>
        <label class="modal-field">Starting qty<input type="number" data-new-item-qty min="0" value="0"></label>
        <label class="modal-field">Low-stock threshold<input type="number" data-new-item-threshold min="0" value="5"></label>
        <p class="modal-error" data-add-item-error hidden></p>
        <div class="modal-actions">
            <button type="button" class="text-button" data-close-add-item>Cancel</button>
            <button type="button" class="save-button" data-confirm-add-item>Save item</button>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-kpi-detail-modal hidden>
    <section class="profile-modal action-modal admin-modal activity-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-kpi-detail type="button">×</button>
        <h2 data-kpi-detail-title>Details</h2>
        <p class="modal-hint" data-kpi-detail-hint></p>
        <div class="activity-list" data-kpi-detail-body></div>
        <div class="modal-actions">
            <button type="button" class="save-button" data-close-kpi-detail>Close</button>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-activity-modal hidden>
    <section class="profile-modal action-modal admin-modal activity-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-activity type="button">×</button>
        <h2 data-activity-title>Activity</h2>
        <p class="modal-hint" data-activity-hint>Select a transaction to see full customer and purchase details.</p>

        <div data-activity-view="list">
            <div class="activity-list" data-activity-list></div>
        </div>

        <div data-activity-view="detail" hidden>
            <button type="button" class="text-button activity-back" data-activity-back>← Back to list</button>
            <div class="activity-detail" data-activity-detail></div>
        </div>

        <div class="modal-actions">
            <button type="button" class="save-button" data-close-activity>Close</button>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-procurement-modal hidden>
    <section class="profile-modal action-modal admin-modal receive-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-procurement type="button">×</button>
        <h2>Record stock receipt</h2>
        <p class="modal-hint">Enter what the invoice shows, then confirm the quantity you actually received. Inventory updates only after you save.</p>
        <label class="modal-field">Invoice number
            <input type="text" data-po-invoice placeholder="e.g. INV-2026-0142">
        </label>
        <label class="modal-field">Invoice date
            <input type="date" data-po-invoice-date>
        </label>
        <label class="modal-field">Supplier
            <input type="text" data-po-supplier placeholder="Name on the invoice">
        </label>
        <label class="modal-field">Inventory item
            <select data-po-item></select>
        </label>
        <label class="modal-field">Qty on invoice
            <input type="number" data-po-qty-invoiced min="0" value="1">
        </label>
        <label class="modal-field">Qty received <small>(confirmed count)</small>
            <input type="number" data-po-qty min="1" value="1">
        </label>
        <label class="modal-field">Invoice total (₱) <small>(optional · logged as expense)</small>
            <input type="number" data-po-cost min="0" step="0.01" placeholder="0.00">
        </label>
        <label class="modal-field">Notes <small>(optional)</small>
            <input type="text" data-po-notes placeholder="e.g. Short 2 pcs vs invoice · accepted">
        </label>
        <p class="modal-error" data-po-error hidden></p>
        <div class="modal-actions">
            <button type="button" class="text-button" data-close-procurement>Cancel</button>
            <button type="button" class="save-button" data-confirm-procurement>Confirm receipt &amp; update stock</button>
        </div>
    </section>
</div>

{{-- Notice modal --}}

<div class="modal-backdrop" data-confirm-modal hidden>
    <section class="profile-modal action-modal admin-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-confirm type="button">×</button>
        <h2 data-confirm-title>Confirm</h2>
        <p class="modal-hint" data-confirm-body></p>
        <div class="modal-actions">
            <button type="button" class="text-button" data-close-confirm>Cancel</button>
            <button type="button" class="save-button" data-confirm-yes>Yes, continue</button>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-notice-modal hidden>
    <section class="profile-modal action-modal admin-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-notice type="button">×</button>
        <h2 data-notice-title>Notice</h2>
        <p data-notice-body class="modal-hint"></p>
        <div class="modal-actions">
            <button type="button" class="save-button" data-close-notice>OK</button>
        </div>
    </section>
</div>

{{-- Profile modal --}}
<div class="modal-backdrop" data-modal hidden>
    <section class="profile-modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-close-modal type="button">×</button>
        <div data-modal-view="menu">
            <span class="profile-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
            <h2>{{ auth()->user()->name }}</h2>
            <p>{{ auth()->user()->email }}</p>
            <p style="font-size:12px;color:var(--staff-muted);margin-top:4px">Role: Admin</p>
            <div class="settings-options">
                <button data-show-view="profile" type="button">Edit profile settings <span>›</span></button>
                <button data-show-view="password" type="button">Change password <span>›</span></button>
                <button data-show-view="display" type="button">Display settings <span>›</span></button>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="logout-button" type="submit">Log out</button>
                </form>
            </div>
        </div>
        <div data-modal-view="profile" hidden>
            <button class="text-button" data-show-view="menu" type="button">‹ Back</button>
            <h2>Edit profile</h2>
            <livewire:settings.profile-modal-form />
        </div>
        <div data-modal-view="password" hidden>
            <button class="text-button" data-show-view="menu" type="button">‹ Back</button>
            <h2>Change password</h2>
            <livewire:settings.password-modal-form />
        </div>
        <div data-modal-view="display" hidden>
            <button class="text-button" data-show-view="menu" type="button">‹ Back</button>
            <h2>Display settings</h2>
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
    </section>
</div>

</main>
</body>
</html>
