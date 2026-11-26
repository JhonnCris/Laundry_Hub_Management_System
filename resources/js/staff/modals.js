/**
 * Modal show/hide + notice + confirm dialogs
 */
export function createModals() {
    let _confirmCallback = null;
    const focusOrigins = new WeakMap();
    const modalStack = [];
    const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])';

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const activeModal = modalStack.at(-1);
        if (!activeModal || activeModal.hidden) return;
        const focusable = [...activeModal.querySelectorAll(focusableSelector)]
            .filter((element) => element instanceof HTMLElement && !element.closest('[hidden]'));
        if (!focusable.length) {
            event.preventDefault();
            return;
        }
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const showModal = (sel) => {
        const el = document.querySelector(sel);
        if (!el) {
            console.warn('SSK modal not found:', sel);
            return;
        }
        const activeElement = document.activeElement;
        if (activeElement instanceof HTMLElement) {
            focusOrigins.set(el, activeElement);
        }
        const existingIndex = modalStack.indexOf(el);
        if (existingIndex >= 0) modalStack.splice(existingIndex, 1);
        modalStack.push(el);
        el.hidden = false;
        el.removeAttribute('hidden');
        el.style.display = 'grid';
        el.style.pointerEvents = 'auto';
        el.style.visibility = 'visible';
        requestAnimationFrame(() => {
            const focusTarget = el.querySelector('[autofocus], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (focusTarget instanceof HTMLElement) focusTarget.focus();
        });
    };

    const hideModal = (target) => {
        const el = target instanceof HTMLElement ? target : document.querySelector(target);
        if (!el) return;
        el.hidden = true;
        el.setAttribute('hidden', '');
        el.style.display = 'none';
        const stackIndex = modalStack.indexOf(el);
        if (stackIndex >= 0) modalStack.splice(stackIndex, 1);
        const focusOrigin = focusOrigins.get(el);
        if (focusOrigin?.isConnected) focusOrigin.focus();
    };

    /** type: "info" (blue), "success" (green) or "error" (red) colours and labels the dialog. */
    const openNotice = (title, body, type = 'info') => {
        const t = document.querySelector('[data-notice-title]');
        const b = document.querySelector('[data-notice-body]');
        const box = document.querySelector('[data-notice-modal] .profile-modal');
        if (t) t.textContent = title || 'Notice';
        if (b) b.textContent = body || '';
        if (box) box.dataset.type = type;
        showModal('[data-notice-modal]');
    };

    /** Show a failed request as a red dialog: openError('Clock in', err). */
    const openError = (title, err) => openNotice(title, err?.message || 'Something went wrong. Please try again.', 'error');

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
        if (t.closest('[data-close-notice]')) {
            e.preventDefault();
            hideModal('[data-notice-modal]');
            return true;
        }
        return false;
    };

    return { showModal, hideModal, openNotice, openError, openConfirm, handleModalClick };
}
