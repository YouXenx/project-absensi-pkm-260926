import Swal from 'sweetalert2';

const TITLES = {
    success: 'Berhasil',
    error: 'Gagal',
    warning: 'Peringatan',
    info: 'Informasi',
    question: 'Konfirmasi',
};

function primaryColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || undefined;
}

/**
 * Modal alert. `options` accepts any SweetAlert2 option.
 */
export function alert(icon, text, options = {}) {
    return Swal.fire({
        icon,
        title: TITLES[icon],
        text,
        confirmButtonColor: primaryColor(),
        ...options,
    });
}

let toastr = null;

/**
 * Small auto-dismissing notification in the corner (Toastr), for light actions: "data tersimpan", a quick row action.
 * Toastr and jQuery are only downloaded the first time a page shows a toast.
 */
export async function toast(icon, text, { title } = {}) {
    toastr ??= import('./plugins/toastr').then((module) => module.default);

    (await toastr)(icon, text, title);
}

/**
 * Yes/No confirmation. Resolves to true when confirmed.
 */
export async function confirm(text, options = {}) {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Anda yakin?',
        text,
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: primaryColor(),
        ...options,
    });

    return result.isConfirmed;
}

/**
 * Show every message rendered by resources/views/partials/flash.blade.php.
 * success/info/warning become Toastr toasts; errors and messages flagged `toast: false`
 * (or carrying a title/html) are SweetAlert dialogs, because the user has to notice them.
 */
export async function showFlashMessages() {
    const element = document.getElementById('flash-messages');

    if (!element) {
        return;
    }

    let messages = [];

    try {
        messages = JSON.parse(element.textContent);
    } catch {
        return;
    }

    for (const { icon = 'info', text = '', toast: asToast, ...options } of messages) {
        const useModal = asToast === false || icon === 'error' || options.title || options.html;

        await (useModal ? alert(icon, text, options) : toast(icon, text, options));
    }
}

/**
 * Ask before submitting forms / following links marked with data-confirm.
 *   <form method="POST" data-confirm="Hapus data siswa ini?"> ... </form>
 *   <a href="..." data-confirm="Keluar dari aplikasi?">Logout</a>
 */
export function bindConfirmations() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) {
            return;
        }

        // Ajax forms (resources/js/modal.js) ask for confirmation themselves.
        if (form.hasAttribute('data-ajax') || form.closest('[data-modal-form]')) {
            return;
        }

        event.preventDefault();

        if (await confirm(form.dataset.confirm)) {
            form.dataset.confirmed = 'true';
            form.requestSubmit(event.submitter ?? undefined);
        }
    });

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[data-confirm]');

        if (!link) {
            return;
        }

        event.preventDefault();

        if (await confirm(link.dataset.confirm)) {
            window.location.href = link.href;
        }
    });
}

export { Swal };
