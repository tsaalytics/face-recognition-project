export function setupListControls(document) {
    document.querySelectorAll('[data-per-page-form]').forEach((form) => {
        const select = form.querySelector('[data-per-page-select]');
        if (!select) return;
        form.querySelector('[data-per-page-submit]')?.setAttribute('hidden', '');
        select.addEventListener('change', () => form.requestSubmit());
    });

    document.querySelectorAll('[data-list-filter]').forEach((panel) => {
        const summary = panel.querySelector('summary');
        document.addEventListener('click', (event) => {
            if (panel.open && !panel.contains(event.target)) panel.open = false;
        });
        panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && panel.open) {
                panel.open = false;
                summary?.focus();
            }
        });
    });
}
