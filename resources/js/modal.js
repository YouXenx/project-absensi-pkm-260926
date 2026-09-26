import { reloadDataTables } from './datatable';
import { alert, confirm, toast } from './flash';
import { hideLoading, showLoading } from './plugins/loading';

/**
 * Modal create/edit forms (resources/views/components/modal-form.blade.php) and Ajax row actions.
 *
 * Opening:   [data-modal-open="modal-id"] with optional data-modal-url (JSON payload from a create/edit endpoint).
 * Submitting: the form is sent with fetch; 2xx closes the modal, reloads every DataTable on the page and shows
 *             payload.message (toast) or payload.alert (SweetAlert dialog); 422 errors are shown inline.
 * Row actions: <form data-ajax data-confirm="..."> (delete, activate, reset password) are sent the same way.
 */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const FIELD_SELECTOR = 'input:not([type="hidden"]), select, textarea';

async function send(url, { method = 'GET', body } = {}) {
    let response;

    try {
        response = await fetch(url, {
            method,
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
    } catch {
        return { ok: false, status: 0, data: { message: 'Tidak dapat terhubung ke server. Periksa koneksi Anda.' } };
    }

    let data = {};

    try {
        data = await response.json();
    } catch {
        data = {};
    }

    return { ok: response.ok, status: response.status, data };
}

/**
 * SweetAlert for everything that is not an inline validation error.
 */
function showRequestError({ status, data }) {
    if (status === 401) {
        window.location.reload();

        return;
    }

    const message = status === 419
        ? 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.'
        : data.message || (status === 404 ? 'Data tidak ditemukan. Mungkin sudah dihapus.' : 'Terjadi kesalahan. Coba lagi.');

    alert(status === 409 ? 'warning' : 'error', message, status === 403 ? { title: 'Akses ditolak' } : {});
}

function showSuccess(data) {
    if (data.alert) {
        const { icon = 'success', text = '', ...options } = data.alert;

        return alert(icon, text, options);
    }

    return toast('success', data.message || 'Tersimpan.');
}

// ---------------------------------------------------------------------------------------------- modal state

function modalParts(modal) {
    const form = modal.querySelector('form');

    return {
        form,
        title: modal.querySelector('[data-modal-title]'),
        method: form.querySelector('[data-modal-method]'),
        alertBox: modal.querySelector('[data-modal-alert]'),
        alertText: modal.querySelector('[data-modal-alert-text]'),
        loading: modal.querySelector('[data-modal-loading]'),
        fields: modal.querySelector('[data-modal-fields]'),
        submit: modal.querySelector('[data-modal-submit]'),
        busy: modal.querySelector('[data-modal-busy]'),
    };
}

function clearErrors(modal) {
    const { alertBox, alertText } = modalParts(modal);

    modal.querySelectorAll('.field-error[data-modal-error]').forEach((element) => element.remove());
    modal.querySelectorAll('.is-invalid').forEach((element) => element.classList.remove('is-invalid'));
    alertBox.hidden = true;
    alertText.textContent = '';
}

/**
 * Find the input(s) for a Laravel error key: "name", "class_ids.0" (class_ids[]), "attendance.12.status".
 */
function inputsForErrorKey(form, key) {
    const [base, ...rest] = key.split('.');
    const bracketed = base + rest.map((part) => `[${part}]`).join('');
    const candidates = [key, bracketed, `${base}[]`, base];

    for (const name of candidates) {
        const inputs = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)].filter((input) => input.type !== 'hidden' && !input.disabled);

        if (inputs.length > 0) {
            return inputs;
        }
    }

    return [];
}

function showErrors(modal, errors) {
    const { form, alertBox, alertText } = modalParts(modal);
    const unplaced = [];
    const placedFields = new Set();

    Object.entries(errors).forEach(([key, messages]) => {
        const message = [messages].flat()[0];
        const inputs = inputsForErrorKey(form, key);
        const field = inputs[0]?.closest('.field');

        if (!field) {
            unplaced.push(message);

            return;
        }

        inputs.forEach((input) => input.matches('.input, .select') && input.classList.add('is-invalid'));

        if (placedFields.has(field)) {
            return;
        }

        placedFields.add(field);
        const error = document.createElement('div');
        error.className = 'field-error';
        error.dataset.modalError = '';
        error.textContent = message;
        field.append(error);
    });

    if (unplaced.length > 0) {
        alertText.innerHTML = '';
        [...new Set(unplaced)].forEach((message) => {
            const line = document.createElement('div');
            line.textContent = message;
            alertText.append(line);
        });
        alertBox.hidden = false;
    }

    (modal.querySelector('.is-invalid, [data-modal-error]') ?? alertBox).scrollIntoView({ block: 'nearest' });
}

function setMode(modal, mode) {
    modal.dataset.mode = mode;

    modal.querySelectorAll('[data-modal-show]').forEach((section) => {
        const visible = section.dataset.modalShow === mode;
        section.hidden = !visible;
        // Only undo what this function disabled: inputs the server rendered as disabled (e.g. a class without a homeroom teacher) stay disabled.
        section.querySelectorAll('input, select, textarea').forEach((input) => {
            if (!visible && !input.disabled) {
                input.disabled = true;
                input.dataset.modalModeDisabled = '';
            } else if (visible && input.hasAttribute('data-modal-mode-disabled')) {
                input.disabled = false;
                delete input.dataset.modalModeDisabled;
            }
        });
    });
}

function fillValues(modal, values = {}) {
    const { form } = modalParts(modal);

    Object.entries(values).forEach(([key, value]) => {
        modal.querySelectorAll(`[data-modal-text="${CSS.escape(key)}"]`).forEach((element) => {
            if ('value' in element && element.matches('input, textarea')) {
                element.value = value ?? '';
            } else {
                element.textContent = value ?? '';
            }
        });

        const inputs = [...form.querySelectorAll(`[name="${CSS.escape(key)}"], [name="${CSS.escape(key)}[]"]`)];
        const hasCheckables = inputs.some((input) => input.type === 'checkbox' || input.type === 'radio');

        inputs.forEach((input) => {
            if (input.type === 'hidden' && hasCheckables) {
                return; // e.g. <input type="hidden" name="is_active" value="0"> behind a checkbox
            }

            if (input.type === 'checkbox') {
                input.checked = Array.isArray(value) ? value.map(String).includes(input.value) : value === true || String(value) === input.value;
            } else if (input.type === 'radio') {
                input.checked = String(value) === input.value;
            } else if (input.type !== 'password' && input.type !== 'file') {
                input.value = value ?? '';
            }
        });
    });
}

function fillSlots(modal, slots = {}) {
    Object.entries(slots).forEach(([name, html]) => {
        modal.querySelectorAll(`[data-modal-slot="${CSS.escape(name)}"]`).forEach((element) => {
            element.innerHTML = html;
        });
    });
}

/**
 * Select2 widgets are rebuilt every time the modal opens: options (slots), values and the disabled state
 * change per record, and form.reset() does not update the widget.
 */
async function enhanceModalSelects(modal) {
    if (modal.querySelector('select[data-searchable]')) {
        const { enhanceSelects } = await import('./plugins/select2');
        enhanceSelects(modal);
    }
}

function focusFirstField(modal) {
    const first = [...modal.querySelectorAll(`[data-modal-fields] ${FIELD_SELECTOR}`)]
        .find((input) => !input.disabled && !input.readOnly && input.offsetParent !== null && !input.classList.contains('select2-hidden-accessible'));

    first?.focus();
}

let lastOpener = null;

function closeModal(modal) {
    modal.hidden = true;
    document.body.classList.remove('has-modal-open');
    lastOpener?.focus?.();
}

function applyPayload(modal, payload) {
    const { form, title, method } = modalParts(modal);
    const mode = modal.dataset.mode;

    if (payload.action) {
        form.action = payload.action;
    }

    if (payload.method) {
        method.value = payload.method;
    }

    title.textContent = payload.title ?? (mode === 'edit' ? modal.dataset.titleEdit : modal.dataset.titleCreate);
    fillSlots(modal, payload.slots);
    setMode(modal, mode); // slots may have added inputs to a mode-specific section
    fillValues(modal, payload.values);

    if (payload.confirm) {
        form.dataset.modalConfirm = payload.confirm;
    }
}

async function openModal(modal, opener) {
    const { form, title, method, loading, fields, submit } = modalParts(modal);
    const url = opener?.dataset.modalUrl;
    const mode = opener?.dataset.modalMode ?? (url && !opener?.hasAttribute('data-modal-create') ? 'edit' : 'create');

    lastOpener = opener;
    form.reset();
    clearErrors(modal);
    delete form.dataset.modalConfirm;
    form.action = modal.dataset.actionCreate;
    method.value = modal.dataset.methodCreate;
    title.textContent = mode === 'edit' ? modal.dataset.titleEdit : modal.dataset.titleCreate;
    setMode(modal, mode);

    if (url) {
        loading.hidden = false;
        fields.hidden = true;
        submit.disabled = true;
    }

    modal.hidden = false;
    document.body.classList.add('has-modal-open');

    if (!url) {
        await enhanceModalSelects(modal);
        focusFirstField(modal);

        return;
    }

    const result = await send(url);

    loading.hidden = true;
    fields.hidden = false;
    submit.disabled = false;

    if (!result.ok) {
        closeModal(modal);
        showRequestError(result);

        return;
    }

    applyPayload(modal, result.data);
    await enhanceModalSelects(modal);
    focusFirstField(modal);
}

async function submitModal(modal) {
    const { form, submit } = modalParts(modal);

    if (form.dataset.modalConfirm && !(await confirm(form.dataset.modalConfirm))) {
        return;
    }

    const { busy } = modalParts(modal);

    clearErrors(modal);
    submit.disabled = true;
    submit.classList.add('is-loading');
    busy.hidden = false;

    const result = await send(form.action, { method: 'POST', body: new FormData(form) });

    submit.disabled = false;
    submit.classList.remove('is-loading');
    busy.hidden = true;

    if (result.ok) {
        closeModal(modal);
        reloadDataTables();
        showSuccess(result.data);

        return;
    }

    if (result.status === 422 && result.data.errors) {
        showErrors(modal, result.data.errors);
        toast('error', 'Periksa kembali isian formulir.');

        return;
    }

    if (result.status === 422) {
        showErrors(modal, { _: [result.data.message] });

        return;
    }

    showRequestError(result);
}

// ---------------------------------------------------------------------------------------------- row actions

async function submitAjaxForm(form, submitter) {
    if (form.dataset.confirm && !(await confirm(form.dataset.confirm))) {
        return;
    }

    submitter?.setAttribute('disabled', '');
    showLoading('Memproses…', { delay: 250 });

    const result = await send(form.action, { method: 'POST', body: new FormData(form) });

    hideLoading();
    submitter?.removeAttribute('disabled');

    if (!result.ok) {
        showRequestError(result);

        return;
    }

    reloadDataTables();
    showSuccess(result.data);
}

export function bindModalForms() {
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-modal-open]');

        if (opener) {
            event.preventDefault();
            const modal = document.getElementById(opener.dataset.modalOpen);

            if (modal) {
                openModal(modal, opener);
            }

            return;
        }

        const closer = event.target.closest('[data-modal-close]');

        if (closer) {
            closeModal(closer.closest('[data-modal-form]'));
        }
    });

    // Editing a field clears its inline error.
    ['input', 'change'].forEach((type) => document.addEventListener(type, (event) => {
        const field = event.target.closest?.('[data-modal-form] .field');

        field?.querySelectorAll('[data-modal-error]').forEach((error) => error.remove());
        field?.querySelectorAll('.is-invalid').forEach((input) => input.classList.remove('is-invalid'));
    }));

    // Capture phase: runs before Select2/SweetAlert handle (and close themselves on) the same Esc press.
    document.addEventListener('keydown', (event) => {
        // Esc first closes an open SweetAlert or Select2 dropdown, not the modal behind it.
        if (event.key !== 'Escape' || document.querySelector('.swal2-container, .select2-container--open')) {
            return;
        }

        const modal = document.querySelector('[data-modal-form]:not([hidden])');

        if (modal) {
            closeModal(modal);
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        const modal = form.closest?.('[data-modal-form]');

        if (modal) {
            event.preventDefault();
            submitModal(modal);

            return;
        }

        if (form.hasAttribute?.('data-ajax')) {
            event.preventDefault();
            submitAjaxForm(form, event.submitter);
        }
    });

    // Links from other pages, e.g. /admin/siswa#tambah opens the "Tambah Siswa" modal.
    if (window.location.hash.length > 1) {
        document.querySelector(`[data-modal-hash="${CSS.escape(window.location.hash.slice(1))}"]`)?.click();
    }
}
