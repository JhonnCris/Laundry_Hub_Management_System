/**
 * Sidebar navigation, profile modal, machine status toggles
 */
import { api } from './core.js';

const showModalView = (view) => {
    document.querySelectorAll('[data-modal-view]').forEach((panel) => {
        panel.hidden = panel.dataset.modalView !== view;
    });
};

const syncAppearanceControls = () => {
    const root = document.documentElement;
    const theme = root.getAttribute('data-theme') || 'light';
    const textSize = root.getAttribute('data-text-size') || 'normal';
    document.querySelectorAll('[data-theme-option]').forEach((btn) => {
        btn.classList.toggle('is-selected', btn.dataset.themeOption === theme);
    });
    document.querySelectorAll('[data-text-size-option]').forEach((btn) => {
        btn.classList.toggle('is-selected', btn.dataset.textSizeOption === textSize);
    });
};

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
        showModalView('menu');
        showModal('[data-modal]');
        return true;
    }
    if (t.closest('[data-close-modal]')) {
        e.preventDefault();
        hideModal('[data-modal]');
        return true;
    }

    const showView = t.closest('[data-show-view]');
    if (showView) {
        e.preventDefault();
        showModalView(showView.dataset.showView);
        if (showView.dataset.showView === 'display') syncAppearanceControls();
        return true;
    }

    const themeOpt = t.closest('[data-theme-option]');
    if (themeOpt) {
        e.preventDefault();
        document.documentElement.setAttribute('data-theme', themeOpt.dataset.themeOption);
        try {
            localStorage.setItem('ssk-theme', themeOpt.dataset.themeOption);
        } catch (err) {}
        syncAppearanceControls();
        return true;
    }

    const textOpt = t.closest('[data-text-size-option]');
    if (textOpt) {
        e.preventDefault();
        const size = textOpt.dataset.textSizeOption;
        if (size === 'normal') document.documentElement.removeAttribute('data-text-size');
        else document.documentElement.setAttribute('data-text-size', size);
        try {
            localStorage.setItem('ssk-text-size', size);
        } catch (err) {}
        syncAppearanceControls();
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
