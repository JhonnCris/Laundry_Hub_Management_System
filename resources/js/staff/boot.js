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
import { loadStaffBootstrap } from './bootstrap.js';
import { createTransaction } from './transaction.js';
import { handleSalesClick, handleSalesInput, loadSales } from './sales.js';

export function bootStaffApp() {
    const app = document.querySelector('[data-staff-app]');
    if (!app) return;
    if (app.dataset.sskBound === '1') return;
    app.dataset.sskBound = '1';

    const modals = createModals();
    const { showModal, hideModal, openNotice, openConfirm, handleModalClick } = modals;

    const ctx = {
        app,
        showModal,
        hideModal,
        openNotice,
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
    ctx.loadStaffBootstrap = () => loadStaffBootstrap(app, tx.update).then(() => loadSales(app));

    // Hide overlays on load
    document.querySelectorAll('.modal-backdrop[hidden]').forEach((el) => {
        el.hidden = true;
    });

    app.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof Element)) return;

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
                renderFinanceFiltered(app, app._adminBootstrap, v === 'revenue' ? 'sales' : v === 'expenses' ? 'expenses' : 'net');
            }
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
    const run = () => bootStaffApp();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    document.addEventListener('livewire:navigated', () => {
        const app = document.querySelector('[data-staff-app]');
        if (app) delete app.dataset.sskBound;
        run();
    });
}
