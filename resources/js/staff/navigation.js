/**
 * Sidebar navigation, profile modal, machine status toggles
 */
import { api } from './core.js';
import { currentLocale } from './i18n.js';

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
    document.querySelectorAll('[data-locale-option]').forEach((btn) => {
        btn.classList.toggle('is-selected', btn.dataset.localeOption === currentLocale());
    });
    document.querySelectorAll('[data-text-size-option]').forEach((btn) => {
        btn.classList.toggle('is-selected', btn.dataset.textSizeOption === textSize);
    });
};

/** Open one sidebar screen (and highlight its menu button). */
export function showScreen(app, screen) {
    app.querySelectorAll('[data-screen]').forEach((item) => {
        item.classList.toggle('is-active', item.matches('nav [data-screen]') && item.dataset.screen === screen);
    });
    app.querySelectorAll('[data-panel]').forEach((panel) => {
        panel.classList.toggle('is-visible', panel.dataset.panel === screen);
    });
    app.classList.remove('sidebar-open');
}

export function handleNavigationClick(t, e, ctx) {
    const { app, showModal, hideModal, openNotice, openError } = ctx;

    if (t.closest('[data-guide-customers]')) {
        e.preventDefault();
        showScreen(app, 'customers');
        return true;
    }

    const screenBtn = t.closest('[data-screen]');
    if (screenBtn && app.contains(screenBtn)) {
        e.preventDefault();
        const needsDuty = !!app.querySelector(`nav [data-screen="${screenBtn.dataset.screen}"][data-needs-duty]`);
        if (needsDuty && app.dataset.onDuty !== '1') {
            showScreen(app, 'attendance');
            openNotice('Clock in first', 'Manage Customer and Transactions unlock after you clock in on the Attendance page.', 'error');
            return true;
        }
        showScreen(app, screenBtn.dataset.screen);
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
        if (['display', 'language'].includes(showView.dataset.showView)) syncAppearanceControls();
        return true;
    }

    const localeOpt = t.closest('[data-locale-option]');
    if (localeOpt) {
        e.preventDefault();
        if (localeOpt.dataset.localeOption === currentLocale()) return true;
        api('/ajax/locale', { method: 'POST', body: JSON.stringify({ locale: localeOpt.dataset.localeOption }) })
            .then(() => window.location.reload())
            .catch((err) => openError('Language', err));
        return true;
    }

    if (t.closest('[data-open-help]')) {
        e.preventDefault();
        showModal('[data-help-modal]');
        return true;
    }
    if (t.closest('[data-close-help]')) {
        e.preventDefault();
        hideModal('[data-help-modal]');
        return true;
    }
    const helpTab = t.closest('[data-help-tab]');
    if (helpTab) {
        e.preventDefault();
        document.querySelectorAll('[data-help-tab]').forEach((tab) => tab.classList.toggle('is-selected', tab === helpTab));
        document.querySelectorAll('[data-help-pane]').forEach((pane) => {
            pane.hidden = pane.dataset.helpPane !== helpTab.dataset.helpTab;
        });
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
        api(`/ajax/staff/machines/${id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status: st }),
        })
            .then(() => ctx.loadStaffBootstrap?.())
            .catch((err) => openError('Machine', err));
        return true;
    }

    return false;
}
