/**
 * App bootstrap — wires staff + admin modules
 */
import { createModals } from './modals.js';
import { handleCustomerClick, handleCustomerSearch, renderCustomerContext } from './customers.js';
import { handleAttendanceClick } from './attendance.js';
import { handleInventoryClick } from './inventory.js';
import { handleHistorySearch, handleQueueClick } from './queue.js';
import { handleAdminClick, loadAdminBootstrap, renderFinanceFiltered } from './admin.js';
import { handleNavigationClick } from './navigation.js';
import { loadStaffBootstrap, renderBaskets } from './bootstrap.js';
import { toast } from './core.js';
import { applyLocale, currentLocale } from './i18n.js';
import { createTransaction } from './transaction.js';
import { handleSalesClick, handleSalesInput, loadSales } from './sales.js';

export function bootStaffApp() {
    const app = document.querySelector('[data-staff-app]');
    if (!app) return;
    if (app.dataset.sskBound === '1') return;
    app.dataset.sskBound = '1';

    const modals = createModals();
    const { showModal, hideModal, openNotice, openError, openConfirm, handleModalClick } = modals;

    const ctx = {
        app,
        showModal,
        hideModal,
        openNotice,
        openError,
        openConfirm,
        update: null,
        resetTransactionForm: null,
        loadStaffBootstrap: null,
    };

    const tx = createTransaction(app, {
        showModal,
        hideModal,
        openNotice,
        loadStaffBootstrap: () => ctx.loadStaffBootstrap(),
    });
    ctx.update = tx.update;
    ctx.resetTransactionForm = tx.resetTransactionForm;
    ctx.loadStaffBootstrap = () => loadStaffBootstrap(app, tx.update);

    // Hide overlays on load
    document.querySelectorAll('.modal-backdrop[hidden]').forEach((el) => {
        el.hidden = true;
    });

    app.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof Element)) return;

        if (t.closest('[data-screen="sales"]')) loadSales(app);
        if (handleModalClick(t, e)) return;
        if (handleNavigationClick(t, e, ctx)) return;
        if (handleAttendanceClick(t, e, ctx)) return;
        if (handleCustomerClick(t, e, ctx)) return;
        if (handleAdminClick(t, e, ctx)) return;
        if (handleInventoryClick(t, e, ctx)) return;
        if (handleQueueClick(t, e, ctx)) return;
        if (handleSalesClick(t, e, app, showModal)) return;
        if (tx.handleTransactionClick(t, e)) return;
    });

    app.addEventListener('input', (e) => {
        const t = e.target;
        if (!(t instanceof Element)) return;
        if (handleCustomerSearch(t, app)) return;
        if (handleHistorySearch(t)) return;
        if (tx.handleTransactionInput(t)) return;
    });

    app.addEventListener('change', (e) => {
        const t = e.target;
        if (!(t instanceof Element)) return;
        if (t.matches('[data-sum-type-filter]')) {
            const v = t.value || 'all';
            if (app._adminBootstrap) {
                renderFinanceFiltered(app, app._adminBootstrap, v === 'revenue' ? 'sales' : v === 'expenses' ? 'expenses' : v === 'cancelled' ? 'cancelled' : 'net');
            }
            return;
        }
        if (t.matches('[data-basket-filter]')) {
            app._basketFilter = t.value;
            if (app._bootstrap) renderBaskets(app, app._bootstrap);
            return;
        }
        if (handleSalesInput(t, app)) return;
        if (tx.handleTransactionInput(t)) return;
    });

    // Keyboard: KPI cards act as buttons; Esc closes the top-most open modal
    app.addEventListener('keydown', (e) => {
        const card = e.target instanceof Element ? e.target.closest('[role="button"][data-kpi], [role="button"][data-sum-kpi], [role="button"][data-inv-kpi]') : null;
        if (card && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            card.click();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const open = [...document.querySelectorAll('.modal-backdrop')].filter((el) => !el.hidden && el.style.display !== 'none');
        const top = open[open.length - 1];
        if (top) {
            top.hidden = true;
            top.style.display = 'none';
        }
    });


    // also payment cash may be outside app root in some layouts
    document.addEventListener('input', (e) => {
        const t = e.target;
        if (!(t instanceof Element)) return;
        if (t.matches('[data-payment-cash]')) tx.handleTransactionInput(t);
    });

    if (app.dataset.role !== 'admin') {
        renderCustomerContext(app);
        ctx.loadStaffBootstrap();
    }
    if (app.dataset.role === 'admin') {
        loadAdminBootstrap(app);
    }

    tx.update();
}

export function start() {
    // Anything that slips past a screen's own error handling still reaches the user.
    window.addEventListener('unhandledrejection', (e) => {
        toast(e.reason?.message || 'Something went wrong. Please refresh the page and try again.');
    });
    const run = () => {
        bootStaffApp();
        applyLocale(currentLocale());
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    // After a Livewire page swap there is a new element to bind; bootStaffApp skips an already-bound one.
    document.addEventListener('livewire:navigated', run);
}
