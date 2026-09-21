document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-staff-app]');
    if (!app) return;
    const activeWorkflow = () => app.querySelector('[data-workflow]:not([hidden])');
    const update = () => {
        const workflow = activeWorkflow();
        let amount = Number(workflow.querySelector('[data-service-price]').value) + Number(workflow.querySelector('[data-consumable]').value);
        app.querySelectorAll('.add-on').forEach((item) => amount += Number(item.dataset.price) * Number(item.querySelector('output').value));
        const garments = [...app.querySelectorAll('[data-workflow="drop_off"] [data-garment-count]')].reduce((total, output) => total + Number(output.value), 0);
        app.querySelectorAll('[data-summary-garments]').forEach((element) => element.textContent = garments);
        app.querySelector('[data-total]').textContent = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
    };
    app.querySelectorAll('[data-increase], [data-decrease]').forEach((button) => button.addEventListener('click', () => { const output = button.parentElement.querySelector('output'); output.value = Math.max(0, Number(output.value) + (button.hasAttribute('data-increase') ? 1 : -1)); update(); }));
    app.querySelectorAll('[data-garment-increase], [data-garment-decrease]').forEach((button) => button.addEventListener('click', () => { const output = button.parentElement.querySelector('output'); output.value = Math.max(0, Number(output.value) + (button.hasAttribute('data-garment-increase') ? 1 : -1)); update(); }));
    app.querySelector('[data-clear-items]').addEventListener('click', () => { app.querySelectorAll('.quantity-control output').forEach((output) => output.value = 0); update(); });
    app.querySelectorAll('[data-workflow] select').forEach((input) => input.addEventListener('change', update));
    app.querySelectorAll('[data-service]').forEach((button) => button.addEventListener('click', () => { const selfService = button.dataset.service === 'self_service'; app.querySelectorAll('[data-service]').forEach((item) => item.classList.toggle('is-selected', item === button)); app.querySelector('[data-workflow="drop_off"]').hidden = selfService; app.querySelector('[data-workflow="self_service"]').hidden = !selfService; app.querySelector('[data-summary-service]').textContent = selfService ? 'Self Service' : 'Drop Off'; app.querySelector('[data-summary-tag]').textContent = selfService ? '—' : '#014'; update(); }));
    app.querySelectorAll('[data-screen]').forEach((button) => button.addEventListener('click', () => { app.querySelectorAll('[data-screen]').forEach((item) => item.classList.toggle('is-active', item === button)); app.querySelectorAll('[data-panel]').forEach((panel) => panel.classList.toggle('is-visible', panel.dataset.panel === button.dataset.screen)); app.classList.remove('sidebar-open'); }));
    app.querySelector('[data-sidebar-toggle]').addEventListener('click', () => app.classList.toggle('sidebar-open'));
    const showModalView = (view) => app.querySelectorAll('[data-modal-view]').forEach((panel) => panel.hidden = panel.dataset.modalView !== view);
    app.querySelector('[data-profile]').addEventListener('click', () => { showModalView('menu'); app.querySelector('[data-modal]').hidden = false; });
    app.querySelector('[data-close-modal]').addEventListener('click', () => { app.querySelector('[data-modal]').hidden = true; showModalView('menu'); });
    app.querySelectorAll('[data-show-view]').forEach((button) => button.addEventListener('click', () => {
        showModalView(button.dataset.showView);
        if (button.dataset.showView === 'display') syncAppearanceControls();
    }));

    const root = document.documentElement;
    const syncAppearanceControls = () => {
        const theme = root.getAttribute('data-theme') || 'light';
        const textSize = root.getAttribute('data-text-size') || 'normal';
        app.querySelectorAll('[data-theme-option]').forEach((btn) => btn.classList.toggle('is-selected', btn.dataset.themeOption === theme));
        app.querySelectorAll('[data-text-size-option]').forEach((btn) => btn.classList.toggle('is-selected', btn.dataset.textSizeOption === textSize));
    };
    app.querySelectorAll('[data-theme-option]').forEach((btn) => btn.addEventListener('click', () => {
        root.setAttribute('data-theme', btn.dataset.themeOption);
        try { localStorage.setItem('ssk-theme', btn.dataset.themeOption); } catch (e) {}
        syncAppearanceControls();
    }));
    app.querySelectorAll('[data-text-size-option]').forEach((btn) => btn.addEventListener('click', () => {
        const size = btn.dataset.textSizeOption;
        if (size === 'normal') root.removeAttribute('data-text-size'); else root.setAttribute('data-text-size', size);
        try { localStorage.setItem('ssk-text-size', size); } catch (e) {}
        syncAppearanceControls();
    }));
    syncAppearanceControls();
    app.querySelector('[data-save]').addEventListener('click', () => { const notice = app.querySelector('[data-save-notice]'); notice.textContent = app.querySelector('[data-customer]').value.trim() ? 'Prototype saved. No database record was created.' : 'Enter a customer name before saving.'; notice.classList.toggle('is-error', !app.querySelector('[data-customer]').value.trim()); });
    update();
});
