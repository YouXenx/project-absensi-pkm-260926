import { initDataTables } from './datatable';
import { Swal, alert, bindConfirmations, confirm, showFlashMessages, toast } from './flash';
import { bindModalForms } from './modal';
import { bindPasswordToggles } from './password-toggle';
import { bindShell } from './shell';
import { bindLoadingForms, hideLoading, showLoading } from './plugins/loading';

// Expose the helpers for inline Blade scripts, e.g. window.flash.toast('success', 'Tersimpan').
window.Swal = Swal;
window.flash = { alert, toast, confirm, showLoading, hideLoading };

bindConfirmations();

/**
 * <select data-apply-all="group"> copies its value to every <select data-apply-all-target="group">,
 * e.g. applying one promotion decision to a whole class.
 */
function bindApplyAll() {
    document.addEventListener('change', (event) => {
        const source = event.target.closest('select[data-apply-all]');

        if (!source || source.value === '') {
            return;
        }

        document
            .querySelectorAll(`select[data-apply-all-target="${source.dataset.applyAll}"]`)
            .forEach((target) => {
                target.value = source.value;
            });
    });
}

/**
 * <button data-mark-all="hadir"> checks the radio with that value in every row of its form
 * ("tandai semua hadir"), so the guru only has to change the students who are absent.
 */
function bindMarkAll() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-mark-all]');

        if (!button) {
            return;
        }

        button
            .closest('form')
            .querySelectorAll(`input[type="radio"][value="${button.dataset.markAll}"]`)
            .forEach((radio) => {
                radio.checked = true;
            });
    });
}

/**
 * <select data-auto-submit> submits its form when changed (replaces inline onchange, which Select2 would fire twice).
 */
function bindAutoSubmit() {
    document.addEventListener('change', (event) => {
        const select = event.target.closest?.('select[data-auto-submit]');

        select?.form?.requestSubmit();
    });
}

/**
 * UI plugins are separate chunks, downloaded only by pages that contain their markup.
 */
function loadPagePlugins() {
    if (document.querySelector('select[data-searchable]')) {
        import('./plugins/select2').then(({ enhanceSelects }) => enhanceSelects());
    }

    if (document.querySelector('input[data-datepicker]')) {
        import('./plugins/datepicker').then(({ initDatepickers }) => initDatepickers());
    }

    if (document.querySelector('table[data-export-table]')) {
        import('./plugins/export-buttons').then(({ initStaticExportTables }) => initStaticExportTables());
    }

    if (document.querySelector('[data-slides]')) {
        import('./login-slides').then(({ initSlides }) => initSlides());
    }

    if (document.querySelector('[data-attendance-form]')) {
        import('./attendance').then(({ initAttendanceForm }) => initAttendanceForm());
    }

    if (document.querySelector('canvas[data-chart]')) {
        import('./plugins/charts').then(({ initCharts }) => initCharts());
    }
}

function boot() {
    // The dark mode was removed; drop the preference left over from the Adminator template.
    try {
        localStorage.removeItem('dash26-theme');
    } catch {
        // Ignore: private mode or blocked storage.
    }

    bindShell();
    showFlashMessages();
    initDataTables();
    bindModalForms();
    bindPasswordToggles();
    bindApplyAll();
    bindMarkAll();
    bindAutoSubmit();
    bindLoadingForms();
    loadPagePlugins();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
