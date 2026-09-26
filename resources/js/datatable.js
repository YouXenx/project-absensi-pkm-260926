import DataTable from 'datatables.net';

/**
 * Server-side DataTables for every [data-datatable] block rendered by <x-data-table>.
 *
 * DataTables only handles the Ajax protocol and row rendering; the search box,
 * page-length select, info text and pager are Adminator markup driven via the API.
 */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

const numberFormat = new Intl.NumberFormat('id-ID');

function debounce(callback, wait = 300) {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), wait);
    };
}

/**
 * Page numbers to show: first, last, current ±1, with "…" gaps.
 */
function pageItems(current, total) {
    const pages = new Set([0, total - 1, current - 1, current, current + 1]);
    const sorted = [...pages].filter((page) => page >= 0 && page < total).sort((a, b) => a - b);
    const items = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            items.push('gap');
        }

        items.push(page);
    });

    return items;
}

function renderPager(pager, table) {
    const { page, pages } = table.page.info();
    const button = (label, target, { active = false, disabled = false, aria } = {}) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'pager-btn' + (active ? ' is-active' : '');
        element.innerHTML = label;
        element.disabled = disabled;

        if (aria) {
            element.setAttribute('aria-label', aria);
        }

        if (target !== null && !disabled && !active) {
            element.addEventListener('click', () => table.page(target).draw('page'));
        }

        return element;
    };

    pager.replaceChildren(
        button('<svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>', page - 1, { disabled: page <= 0, aria: 'Sebelumnya' }),
        ...pageItems(page, Math.max(pages, 1)).map((item) =>
            item === 'gap' ? button('…', null, { disabled: true }) : button(String(item + 1), item, { active: item === page }),
        ),
        button('<svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>', page + 1, { disabled: page >= pages - 1, aria: 'Berikutnya' }),
    );
}

function renderInfo(info, table) {
    const { start, end, recordsDisplay, recordsTotal } = table.page.info();

    if (recordsDisplay === 0) {
        info.textContent = recordsTotal === 0 ? 'Belum ada data' : 'Tidak ada data yang cocok';

        return;
    }

    const filtered = recordsDisplay !== recordsTotal ? ` (disaring dari ${numberFormat.format(recordsTotal)})` : '';
    info.innerHTML = `Menampilkan <strong>${numberFormat.format(start + 1)}–${numberFormat.format(end)}</strong> dari <strong>${numberFormat.format(recordsDisplay)}</strong>${filtered}`;
}

export function initDataTable(wrapper) {
    const tableElement = wrapper.querySelector('table');
    const search = wrapper.querySelector('[data-datatable-search]');
    const length = wrapper.querySelector('[data-datatable-length]');
    const info = wrapper.querySelector('[data-datatable-info]');
    const pager = wrapper.querySelector('[data-datatable-pager]');
    const filters = [...wrapper.querySelectorAll('[data-datatable-filter]')];

    // Row actions (and columns marked data-export="false") are left out of Excel/PDF/print exports.
    const columns = [...tableElement.querySelectorAll('thead th')].map((th) => ({
        data: th.dataset.data,
        orderable: th.dataset.orderable !== 'false',
        className: [th.dataset.class ?? '', th.dataset.data === 'actions' || th.dataset.export === 'false' ? 'no-export' : ''].join(' ').trim(),
    }));

    const firstSortable = columns.findIndex((column) => column.orderable);

    const table = new DataTable(tableElement, {
        serverSide: true,
        processing: true,
        searchDelay: 0,
        pageLength: Number(length?.value ?? 10),
        order: firstSortable >= 0 ? [[firstSortable, 'asc']] : [],
        columns,
        layout: { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null },
        autoWidth: false,
        ajax: {
            url: wrapper.dataset.url,
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            data: (payload) => {
                filters.forEach((filter) => {
                    payload[filter.dataset.datatableFilter] = filter.value;
                });
            },
            error: () => {
                window.flash?.toast('error', 'Gagal memuat data. Coba muat ulang halaman.');
            },
        },
        language: {
            processing: '<span class="spinner sm"></span><span>Memuat data…</span>',
            emptyTable: 'Belum ada data',
            zeroRecords: 'Tidak ada data yang cocok',
        },
    });

    // Optional "fragments" in the response ({selector: html}) refresh markup next to the table, e.g. summary cards.
    table.on('xhr', (event, settings, json) => {
        Object.entries(json?.fragments ?? {}).forEach(([selector, html]) => {
            document.querySelectorAll(selector).forEach((element) => {
                element.innerHTML = html;
            });
        });
    });

    table.on('draw', () => {
        renderInfo(info, table);
        renderPager(pager, table);
    });

    if (wrapper.dataset.exportTitle) {
        import('./plugins/export-buttons').then(({ attachExportButtons }) => {
            attachExportButtons(table, wrapper.querySelector('[data-datatable-export]'), {
                title: wrapper.dataset.exportTitle,
                messageTop: wrapper.dataset.exportMessage ?? '',
                url: wrapper.dataset.url,
            });
        });
    }

    search?.addEventListener('input', debounce(() => table.search(search.value).draw()));
    length?.addEventListener('change', () => table.page.len(Number(length.value)).draw());
    filters.forEach((filter) => filter.addEventListener('change', () => table.draw()));

    return table;
}

/**
 * Re-fetch every DataTable on the page, keeping its current page, search, order and filters.
 */
export function reloadDataTables() {
    DataTable.tables({ api: true }).ajax.reload(null, false);
}

export function initDataTables(root = document) {
    return [...root.querySelectorAll('[data-datatable]')].map(initDataTable);
}
