/**
 * Sidebar navigation, profile modal, machine status toggles
 */
import { api } from './core.js';

export function handleNavigationClick(t, e, ctx) {
    const { app, showModal, hideModal, openNotice } = ctx;

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
        return true;
    }

    if (t.closest('[data-sidebar-toggle]')) {
        e.preventDefault();
        app.classList.toggle('sidebar-open');
        return true;
    }

    if (t.closest('[data-profile]')) {
        e.preventDefault();
        showModal('[data-modal]');
        return true;
    }
    if (t.closest('[data-close-modal]')) {
        e.preventDefault();
        hideModal('[data-modal]');
        return true;
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
            .then(() => ctx.loadStaffBootstrap?.())
            .catch((err) => openNotice('Machine', err.message));
        return true;
    }

    const noticeBtn = t.closest('[data-action-notice]');
    if (noticeBtn) {
        e.preventDefault();
        openNotice(noticeBtn.dataset.noticeTitle, noticeBtn.dataset.noticeBody);
        return true;
    }

    return false;
}
