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
            <span>Procurement</span>
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
                <p>Operations and alerts at a glance.</p>
            </div>
            <button class="outline-action" type="button" data-action-notice data-notice-title="Daily report" data-notice-body="Prototype: full sales report export will be available once the database is connected.">View report</button>
        </div>
        <div class="metric-grid">
            <article><span>Active laundry</span><strong>3</strong><small>2 processing · 1 ready</small></article>
            <article><span>Today’s sales</span><strong>₱1,840</strong><small>12 completed orders</small></article>
            <article><span>Low stock</span><strong>2</strong><small>Action required</small></article>
        </div>
        <section class="staff-card full-card">
            <div class="section-heading">
                <h2>Today’s order activity</h2>
                <button class="text-button" type="button" data-action-notice data-notice-title="Orders" data-notice-body="Full order list lives in the Staff operations app. Admins oversee metrics here; staff process laundry on the floor.">About orders</button>
            </div>
            <div class="order-row order-head"><span>Basket</span><span>Customer</span><span>Service</span><span>Status</span></div>
            <div class="order-row"><strong>#014</strong><span>Ana Cruz</span><span>Wash, Dry, Fold</span><em class="status processing">Processing</em></div>
            <div class="order-row"><strong>#013</strong><span>Bea Santos</span><span>Self Service</span><em class="status ready">Ready for pickup</em></div>
        </section>
    </section>

    {{-- Admin: Sales & Summary --}}
    <section class="prototype-panel" data-panel="summary">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Finance</p>
                <h1>Sales & Summary</h1>
                <p>Today’s revenue, operating costs, and service activity.</p>
            </div>
            <button class="outline-action" type="button" data-action-notice data-notice-title="Export summary" data-notice-body="Prototype: export today’s sales and expenses as CSV/PDF once records are stored in the database.">Export summary</button>
        </div>
        <div class="metric-grid">
            <article><span>Total sales</span><strong>₱2,450</strong><small>18 wash · 15 dry</small></article>
            <article><span>Total expenses</span><strong>₱650</strong><small>Consumables & supplies</small></article>
            <article><span>Net amount</span><strong>₱1,800</strong><small>For today</small></article>
        </div>
        <section class="staff-card full-card table-card">
            <div class="order-row order-head"><span>Type</span><span>Description</span><span>Recorded by</span><span>Amount</span></div>
            <div class="order-row"><strong>Income</strong><span>Laundry order #014</span><span>SSK Staff</span><span>₱150.00</span></div>
            <div class="order-row"><strong>Expense</strong><span>Laundry supplies</span><span>SSK Staff</span><span>₱650.00</span></div>
        </section>
    </section>

    {{-- Admin: Manage Inventory --}}
    <section class="prototype-panel" data-panel="manage-inventory">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Stock control</p>
                <h1>Manage Inventory</h1>
                <p>Adjust stock levels, thresholds, and categories for supplies and snacks.</p>
            </div>
            <button class="outline-action" type="button" data-action-notice data-notice-title="Add item" data-notice-body="Prototype: create a new inventory item (name, unit, price, threshold). Will save when inventory APIs are connected.">Add item</button>
        </div>
        <div class="metric-grid">
            <article><span>SKUs tracked</span><strong>4</strong><small>Detergent · snacks · supply</small></article>
            <article><span>Low stock</span><strong>1</strong><small>Downy Sachet</small></article>
            <article><span>Total on-hand value</span><strong>₱1,820</strong><small>Estimated</small></article>
        </div>
        <section class="staff-card full-card table-card">
            <div class="section-heading" style="padding:16px 20px 0;margin-bottom:0"><h2>All items</h2></div>
            <div class="order-row order-head"><span>Item</span><span>Category</span><span>On hand</span><span>Status</span></div>
            <div class="order-row"><strong>Downy Sachet</strong><span>Consumable</span><span>4 sachets</span><em class="status pending">Low stock</em></div>
            <div class="order-row"><strong>Ariel Sachet</strong><span>Detergent</span><span>25 sachets</span><em class="status ready">In stock</em></div>
            <div class="order-row"><strong>Softdrinks</strong><span>Snack / Drink</span><span>40 bottles</span><em class="status ready">In stock</em></div>
            <div class="order-row"><strong>Water Big</strong><span>Snack / Drink</span><span>30 bottles</span><em class="status ready">In stock</em></div>
        </section>
    </section>

    {{-- Admin: Procurement --}}
    <section class="prototype-panel" data-panel="procurement">
        <div class="page-intro">
            <div>
                <p class="eyebrow">Admin · Purchasing</p>
                <h1>Procurement</h1>
                <p>Record purchase orders and restocks from suppliers.</p>
            </div>
            <button class="outline-action" type="button" data-action-notice data-notice-title="New purchase order" data-notice-body="Prototype: create a PO (supplier, items, qty, cost). Saving will update inventory when the backend is connected.">New PO</button>
        </div>
        <div class="metric-grid">
            <article><span>Open POs</span><strong>1</strong><small>Awaiting delivery</small></article>
            <article><span>This month spend</span><strong>₱650</strong><small>Supplies</small></article>
            <article><span>Last restock</span><strong>Today</strong><small>Laundry supplies</small></article>
        </div>
        <section class="staff-card full-card table-card">
            <div class="order-row order-head"><span>PO #</span><span>Supplier / notes</span><span>Status</span><span>Amount</span></div>
            <div class="order-row"><strong>PO-001</strong><span>Laundry supplies restock</span><em class="status ready">Received</em><span>₱650.00</span></div>
            <div class="order-row"><strong>PO-002</strong><span>Downy · 50 sachets</span><em class="status pending">Ordered</em><span>₱500.00</span></div>
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
            <button class="outline-action" type="button" data-action-notice data-notice-title="Add user" data-notice-body="Prototype: create a user with name, email, password, and role (staff or admin). Wire to User model when ready.">Add user</button>
        </div>
        <div class="metric-grid">
            <article><span>Total users</span><strong>2</strong><small>From seeder</small></article>
            <article><span>Admins</span><strong>1</strong><small>admin@ssklabadami.test</small></article>
            <article><span>Staff</span><strong>1</strong><small>staff@ssklabadami.test</small></article>
        </div>
        <section class="staff-card full-card table-card">
            <div class="order-row order-head"><span>Name</span><span>Email</span><span>Role</span><span>Status</span></div>
            <div class="order-row"><strong>SSK Admin</strong><span>admin@ssklabadami.test</span><span>Admin</span><em class="status ready">Active</em></div>
            <div class="order-row"><strong>SSK Staff</strong><span>staff@ssklabadami.test</span><span>Staff</span><em class="status ready">Active</em></div>
        </section>
    </section>
</section>

{{-- Notice modal --}}
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
