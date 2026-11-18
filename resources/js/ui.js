const confirmationDialogs = new WeakMap();

function showConfirmation(dialog, message, approve) {
    let state = confirmationDialogs.get(dialog);
    if (!state) {
        state = { approve: null };
        confirmationDialogs.set(dialog, state);
        dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
        dialog.querySelector('[data-confirm-accept]').addEventListener('click', () => {
            const callback = state.approve;
            state.approve = null;
            dialog.close();
            callback?.();
        });
        dialog.addEventListener('close', () => { state.approve = null; });
        dialog.addEventListener('cancel', () => { state.approve = null; });
    }
    if (dialog.open) return;
    state.approve = approve;
    dialog.querySelector('[data-confirm-message]').textContent = message;
    dialog.showModal();
}

export function setupSubmission(form, browser = window) {
    const button = form.querySelector('[data-submit-button]') || form.querySelector('button[type="submit"], button:not([type])')
        || (form.id ? form.ownerDocument?.querySelector(`[form="${form.id}"][data-profile-save]`) : null);
    if (!button) return;
    const originalLabel = button.textContent;
    let busy = false, approved = false;
    const reset = () => {
        busy = false;
        approved = false;
        button.disabled = false;
        button.textContent = originalLabel;
        form.removeAttribute('aria-busy');
    };
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (busy) { event.preventDefault(); return; }
        if (!form.reportValidity()) { event.preventDefault(); return; }
        if (form.dataset?.confirm && !approved) {
            const dialog = form.ownerDocument?.querySelector('[data-confirm-dialog]');
            if (dialog) {
                event.preventDefault();
                showConfirmation(dialog, form.dataset.confirm, () => { approved = true; form.requestSubmit(); });
                return;
            }
            if (!browser.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        }
        approved = false;
        busy = true;
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        button.textContent = 'Memproses...';
    });
    browser.addEventListener('pageshow', reset);
}
export function setupNavigation(button, navigation) {
    if (!navigation) return;
    const setExpanded = (value) => {
        button.setAttribute('aria-expanded', String(value));
        navigation.classList.toggle('hidden', !value);
    };
    button.addEventListener('click', () => setExpanded(button.getAttribute('aria-expanded') !== 'true'));
    navigation.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            setExpanded(false); button.focus();
        }
    });
}
export function setupDecision(form) {
    const decision = form.querySelector('[name="decision"]');
    const notes = form.querySelector('[name="notes"]');
    if (!decision || !notes) return;
    const update = () => {
        notes.required = decision.value === 'rejected';
        notes.setAttribute('aria-required', String(notes.required));
    };
    decision.addEventListener('change', update);
    update();
}
export function setupEvidencePreview(image, message) {
    const failed = () => {
        image.classList.add('hidden');
        message?.classList.remove('hidden');
    };
    image.addEventListener('error', failed);
    if (image.complete && image.naturalWidth === 0) failed();
}
