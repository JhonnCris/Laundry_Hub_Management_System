/**
 * Modal show/hide + notice + confirm dialogs
 */
export function createModals() {
    let _confirmCallback = null;

    const showModal = (sel) => {
        const el = document.querySelector(sel);
        if (!el) return;
        el.hidden = false;
        el.removeAttribute('hidden');
    };

    const hideModal = (sel) => {
        const el = document.querySelector(sel);
        if (!el) return;
        el.hidden = true;
        el.setAttribute('hidden', '');
    };

    const openNotice = (title, body) => {
        const t = document.querySelector('[data-notice-title]');
        const b = document.querySelector('[data-notice-body]');
        if (t) t.textContent = title || 'Notice';
        if (b) b.textContent = body || '';
        showModal('[data-notice-modal]');
    };

    const openConfirm = (title, body, onYes) => {
        const tEl = document.querySelector('[data-confirm-title]');
        const bEl = document.querySelector('[data-confirm-body]');
        if (tEl) tEl.textContent = title || 'Confirm';
        if (bEl) bEl.textContent = body || '';
        _confirmCallback = typeof onYes === 'function' ? onYes : null;
        showModal('[data-confirm-modal]');
    };

    /** Handle confirm / notice close clicks. Returns true if handled. */
    const handleModalClick = (t, e) => {
        if (t.closest('[data-close-confirm]')) {
            e.preventDefault();
            _confirmCallback = null;
            hideModal('[data-confirm-modal]');
            return true;
        }
        if (t.closest('[data-confirm-yes]')) {
            e.preventDefault();
            const cb = _confirmCallback;
            _confirmCallback = null;
            hideModal('[data-confirm-modal]');
            if (cb) cb();
            return true;
        }
        if (t.closest('[data-close-notice]') || (t.closest('[data-notice-modal]') && t.classList?.contains('modal-backdrop'))) {
            // only close button
        }
        if (t.closest('[data-close-notice]')) {
            e.preventDefault();
            hideModal('[data-notice-modal]');
            return true;
        }
        return false;
    };

    return { showModal, hideModal, openNotice, openConfirm, handleModalClick };
}
