import $ from 'jquery';
import select2 from 'select2';
import 'select2/dist/css/select2.css';

/**
 * Select2 for <select data-searchable> dropdowns with many options (guru, kelas, mapel), including those inside
 * modal forms. Loaded on demand only on pages that have such a select.
 *
 * The rest of the app listens to native "change" events (DataTables filters, modal error clearing, auto-submit),
 * but Select2 only triggers jQuery events; select2:select/clear are re-dispatched as native events.
 *
 * The marker is deliberately not "data-select2": Select2 reads jQuery .data('select2') to find an existing instance,
 * and an empty data-select2 attribute makes it call destroy() on a string.
 */

select2(window, $);

const language = {
    noResults: () => 'Tidak ada hasil',
    searching: () => 'Mencari…',
    errorLoading: () => 'Gagal memuat data',
    removeAllItems: () => 'Hapus semua',
};

function dispatchNativeChange(event) {
    event.currentTarget.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * (Re)build Select2 on every select[data-searchable] inside root. Safe to call repeatedly: a modal calls it each time it
 * opens, after its options (slots), values and disabled state have been filled in, so the widget always matches the select.
 */
export function enhanceSelects(root = document) {
    root.querySelectorAll('select[data-searchable]').forEach((select) => {
        const $select = $(select);

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.off('.nativeBridge').select2('destroy');
        }

        const modalPanel = select.closest('.modal-panel');
        const optionCount = select.options.length;
        // Select2 hides the value="" option ("Semua kelas") once something is picked; an optional select gets a × to go back to it.
        const hasEmptyOption = select.querySelector('option[value=""]') !== null;

        $select.select2({
            width: select.dataset.searchableWidth ?? '100%',
            language,
            placeholder: select.dataset.placeholder ?? select.querySelector('option[value=""]')?.textContent.trim() ?? '',
            allowClear: select.hasAttribute('data-searchable-clear') || (hasEmptyOption && !select.required),
            // A search box only helps when there is something to search through.
            minimumResultsForSearch: optionCount > 8 ? 0 : Infinity,
            // Inside a modal the dropdown must live in the modal, or it renders under the backdrop and loses focus handling.
            dropdownParent: modalPanel ? $(modalPanel) : $(document.body),
        });

        $select.on('select2:select.nativeBridge select2:clear.nativeBridge', dispatchNativeChange);
    });
}

export function isSelect2Open() {
    return document.querySelector('.select2-container--open') !== null;
}
