// resources/js/dialog.js
// Custom modal dialogs + toasts — replaces native confirm()/alert().

let overlayEl = null;
let pendingResolve = null;

function buildOverlay(contentHtml) {
    closeDialog();
    overlayEl = document.createElement('div');
    overlayEl.className = 'modal-overlay';
    overlayEl.innerHTML = `<div class="modal-card" role="dialog" aria-modal="true">${contentHtml}</div>`;
    document.body.appendChild(overlayEl);
    document.body.style.overflow = 'hidden';
    return overlayEl.querySelector('.modal-card');
}

function closeDialog(result = false) {
    const resolve = pendingResolve;
    pendingResolve = null;
    if (overlayEl) {
        overlayEl.remove();
        overlayEl = null;
        document.body.style.overflow = '';
    }
    if (resolve) resolve(result);
}

/** Simple confirm dialog — replaces confirm(). Returns Promise<boolean>. */
export function confirmDialog({ title = 'Are you sure?', message = '', confirmLabel = 'Confirm', danger = false } = {}) {
    return new Promise((resolve) => {
        const card = buildOverlay(`
            <h3 class="modal-title">${title}</h3>
            ${message ? `<p class="modal-message">${message}</p>` : ''}
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-modal-cancel>Cancel</button>
                <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-modal-confirm>${confirmLabel}</button>
            </div>
        `);
        pendingResolve = resolve;
        card.querySelector('[data-modal-confirm]').addEventListener('click', () => closeDialog(true));
        card.querySelector('[data-modal-cancel]').addEventListener('click', () => closeDialog(false));
    });
}

/** Alert dialog — replaces alert(). Returns Promise<void>. */
export function alertDialog({ title = 'Notice', message = '' } = {}) {
    return new Promise((resolve) => {
        const card = buildOverlay(`
            <h3 class="modal-title">${title}</h3>
            <p class="modal-message">${message}</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary" data-modal-confirm>OK</button>
            </div>
        `);
        pendingResolve = resolve;
        card.querySelector('[data-modal-confirm]').addEventListener('click', () => closeDialog(true));
        card.querySelector('[data-modal-confirm]').focus();
    });
}

/**
 * GitHub-style delete confirmation — requires typing the entity name.
 * Returns Promise<boolean>.
 */
export function confirmDelete({ entityType = 'item', name = '', message = '' } = {}) {
    return new Promise((resolve) => {
        const card = buildOverlay(`
            <h3 class="modal-title">Delete ${entityType}</h3>
            <p class="modal-message">${message || `This will permanently delete <strong>${name}</strong>. This cannot be undone.`}</p>
            <p class="modal-hint">To confirm, type <code class="modal-code">${name}</code> below:</p>
            <input type="text" class="form-input modal-input" id="modal-confirm-input" autocomplete="off" spellcheck="false" placeholder="${name}">
            <button type="button" class="btn btn-danger w-full modal-delete-btn" data-modal-confirm disabled>
                I understand the consequences, delete this ${entityType}
            </button>
            <div class="modal-actions" style="margin-top:8px;justify-content:center">
                <button type="button" class="btn btn-ghost" data-modal-cancel>Cancel</button>
            </div>
        `);
        pendingResolve = resolve;

        const input = card.querySelector('#modal-confirm-input');
        const confirmBtn = card.querySelector('[data-modal-confirm]');
        input.focus();

        input.addEventListener('input', () => {
            confirmBtn.disabled = input.value.trim() !== name;
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && input.value.trim() === name) closeDialog(true);
        });
        confirmBtn.addEventListener('click', () => closeDialog(true));
        card.querySelector('[data-modal-cancel]').addEventListener('click', () => closeDialog(false));
    });
}

/** Toast notification — appends to #toasts container. */
export function toast(message, type = 'default', duration = 4000) {
    const container = document.getElementById('toasts');
    if (!container) return;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => el.remove(), duration);
}

// Expose globally — app.js is loaded on every page, so views can call
// ObscuraDialog.confirmDelete(...) without a dynamic module import.
window.ObscuraDialog = { confirmDialog, alertDialog, confirmDelete, toast };

// Global Escape + backdrop click
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlayEl) closeDialog(false);
});
document.addEventListener('click', (e) => {
    if (overlayEl && e.target === overlayEl) closeDialog(false);
});
