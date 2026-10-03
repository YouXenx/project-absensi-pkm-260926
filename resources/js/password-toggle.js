/**
 * Show/hide button of <x-password-input>. Delegated, so it also works for fields inside modals.
 */

function setVisible(button, visible) {
    const input = button.closest('.password-field')?.querySelector('input');

    if (!input) {
        return;
    }

    const label = visible ? 'Sembunyikan password' : 'Tampilkan password';

    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', label);
    button.title = label;
}

export function bindPasswordToggles() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');

        if (button) {
            setVisible(button, button.getAttribute('aria-pressed') !== 'true');
        }
    });

    // A reset form (a modal that is opened again) must not come back with a readable password field.
    document.addEventListener('reset', (event) => {
        event.target.querySelectorAll('[data-password-toggle][aria-pressed="true"]').forEach((button) => setVisible(button, false));
    });
}
