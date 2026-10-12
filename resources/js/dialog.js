/**
 * Native <dialog> modals marked with [data-dialog]:
 *   <button data-dialog-open="some-id">…</button>     opens <dialog id="some-id" data-dialog>
 *   <button data-dialog-close>…</button>              closes the dialog it is in
 * A click on the dimmed area closes too, and a dialog with [data-dialog-autoopen] opens with the page
 * (used to show a rejected upload again). The browser handles focus and Escape.
 */
export function bindDialogs() {
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-dialog-open]');

        if (opener) {
            document.getElementById(opener.dataset.dialogOpen)?.showModal();

            return;
        }

        if (event.target.closest('[data-dialog-close]')) {
            event.target.closest('dialog')?.close();

            return;
        }

        // A click on the backdrop lands on the <dialog> element itself.
        if (event.target.matches('dialog[data-dialog]')) {
            event.target.close();
        }
    });

    document.querySelectorAll('dialog[data-dialog-autoopen]').forEach((dialog) => dialog.showModal());
}
