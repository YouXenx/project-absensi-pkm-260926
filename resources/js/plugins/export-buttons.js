import DataTable from 'datatables.net';
import 'datatables.net-buttons';
import { showLoading, hideLoading } from './loading';

/**
 * DataTables Buttons: Excel, PDF and Print above a listing.
 *
 *  - Server-side tables (<x-data-table export-title="...">) only hold one page in the browser, so an export first
 *    fetches every filtered row from the same endpoint (?export=1, search/order/filters as currently shown).
 *  - Client-side tables (<table data-export-table data-export-title="...">, e.g. the recap) export what is on the page.
 *
 * JSZip (Excel) and pdfmake (PDF, ~1 MB with fonts) are only downloaded when their button is clicked.
 * Columns marked with the "no-export" class (row actions) are left out.
 */

const EXPORT_COLUMNS = ':not(.no-export)';

// YYYY-MM-DD in the browser's timezone (toISOString would give yesterday's date in the early WIB morning).
const localDate = () => new Intl.DateTimeFormat('sv-SE').format(new Date());

async function loadJsZip() {
    if (!DataTable.Buttons.jszip()) {
        const { default: JSZip } = await import('jszip');
        DataTable.Buttons.jszip(JSZip);
    }
}

async function loadPdfMake() {
    if (!DataTable.Buttons.pdfMake()) {
        const [{ default: pdfMake }, { default: vfs }] = await Promise.all([
            import('pdfmake/build/pdfmake'),
            import('pdfmake/build/vfs_fonts'),
        ]);

        if (typeof pdfMake.addVirtualFileSystem === 'function') {
            pdfMake.addVirtualFileSystem(vfs);
        } else {
            pdfMake.vfs = vfs;
        }

        DataTable.Buttons.pdfMake(pdfMake);
    }
}

/**
 * Every filtered row of a server-side table, as plain text for the exported columns.
 */
async function fetchAllRows(dt, url) {
    const params = { ...dt.ajax.params(), start: 0, length: -1, export: 1 };
    const exportedKeys = dt.columns(EXPORT_COLUMNS).indexes().toArray().map((index) => dt.column(index).dataSrc());

    const query = new URLSearchParams();
    const append = (prefix, value) => {
        if (value !== null && typeof value === 'object') {
            Object.entries(value).forEach(([key, nested]) => append(`${prefix}[${key}]`, nested));
        } else if (value !== undefined && value !== null) {
            query.append(prefix, value);
        }
    };
    Object.entries(params).forEach(([key, value]) => append(key, value));

    const separator = url.includes('?') ? '&' : '?';
    const response = await fetch(`${url}${separator}${query}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error(`Export gagal (${response.status})`);
    }

    const json = await response.json();
    const strip = (value) => DataTable.Buttons.stripData(value ?? '', { stripHtml: true, trim: true, stripNewlines: true, decodeEntities: true, escapeExcelFormula: true });

    return json.data.map((row) => exportedKeys.map((key) => strip(String(row[key] ?? ''))));
}

/**
 * pdfmake sizes a table to its content, which left half of the page empty. This gives every column a share of
 * the full page width in proportion to its longest text (within limits, so one long name cannot starve the rest).
 */
function fitPdfToPage(doc) {
    const block = doc.content.find((item) => item.table);

    if (!block) {
        return;
    }

    const { body } = block.table;
    const weights = body[0].map((_, column) => {
        const longest = Math.max(...body.map((row) => String(row[column]?.text ?? row[column] ?? '').length));

        return Math.min(Math.max(longest, 8), 36);
    });
    const total = weights.reduce((sum, weight) => sum + weight, 0);

    block.table.widths = weights.map((weight) => `${((weight / total) * 100).toFixed(2)}%`);
    doc.pageMargins = [28, 30, 28, 30];
    doc.styles.tableHeader = { ...doc.styles.tableHeader, alignment: 'left', fillColor: '#1e3a8a', color: '#ffffff' };
    doc.styles.title = { ...doc.styles.title, bold: true };
}

function exportButton(kind, { label, icon, url, title, messageTop, load }) {
    const base = DataTable.ext.buttons[kind];

    return {
        text: `<svg viewBox="0 0 24 24" aria-hidden="true">${icon}</svg><span>${label}</span>`,
        className: `btn btn--ghost dt-export-btn dt-export-${kind}`,
        attr: { title: `${label} (${title})` },
        action: async function (event, dt, button, config, done) {
            showLoading(url ? 'Menyiapkan data untuk diekspor…' : 'Menyiapkan file…');

            try {
                await load?.();

                const rows = url ? await fetchAllRows(dt, url) : null;
                const exportConfig = {
                    ...base,
                    ...config,
                    title,
                    messageTop,
                    filename: `${title.replace(/[\\/:*?"<>|]+/g, '-')} ${localDate()}`,
                    // Narrow tables read better upright; either way the table spans the page (fitPdfToPage).
                    orientation: dt.columns(EXPORT_COLUMNS).count() <= 4 ? 'portrait' : 'landscape',
                    pageSize: 'A4',
                    customize: kind === 'pdfHtml5' ? fitPdfToPage : config.customize,
                    exportOptions: {
                        columns: EXPORT_COLUMNS,
                        customizeData: rows ? (data) => { data.body = rows; } : null,
                    },
                };

                base.action.call(this, event, dt, button, exportConfig, done);
            } catch (error) {
                window.flash?.alert('error', 'Data gagal diekspor. Coba lagi.');
                done?.();
                console.error(error);
            } finally {
                hideLoading();
            }
        },
    };
}

/**
 * @param {import('datatables.net').Api} table
 * @param {HTMLElement} target  element the button group is appended to
 * @param {{ title: string, messageTop?: string, url?: string }} options  url: server-side endpoint to export all rows from
 */
export function attachExportButtons(table, target, { title, messageTop = '', url = null }) {
    const shared = { url, title, messageTop };

    const buttons = new DataTable.Buttons(table, {
        dom: { container: { className: 'dt-export-buttons' }, button: { className: '' } },
        buttons: [
            exportButton('excelHtml5', { ...shared, label: 'Excel', icon: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13l4 5M12 13l-4 5"/>', load: loadJsZip }),
            exportButton('pdfHtml5', { ...shared, label: 'PDF', icon: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 18h4"/>', load: loadPdfMake }),
            exportButton('print', { ...shared, label: 'Cetak', icon: '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>' }),
        ],
    });

    target.append(buttons.container().get(0));
}

/**
 * Client-side export for server-rendered tables that are already complete on the page (e.g. the attendance recap).
 */
export function initStaticExportTables(root = document) {
    root.querySelectorAll('table[data-export-table]').forEach((tableElement) => {
        const table = new DataTable(tableElement, {
            paging: false,
            searching: false,
            info: false,
            ordering: true,
            order: [],
            autoWidth: false,
            layout: { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null },
            language: { emptyTable: tableElement.dataset.emptyText ?? 'Tidak ada data' },
        });

        const target = document.querySelector(tableElement.dataset.exportTarget) ?? tableElement.parentElement;

        attachExportButtons(table, target, {
            title: tableElement.dataset.exportTitle,
            messageTop: tableElement.dataset.exportMessage ?? '',
        });
    });
}
