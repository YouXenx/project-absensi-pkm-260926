{{--
    Server-side DataTable styled with Adminator's data-table markup; wired up by resources/js/datatable.js.

    <x-data-table :url="route('admin.siswa.data')" search-placeholder="Cari NIS atau nama…" export-title="Data Siswa">
        <x-slot:filters> ...optional <select data-datatable-filter="class_id"> ... </x-slot:filters>
        <th data-data="nis">NIS</th>
        <th data-data="actions" data-orderable="false"></th>
    </x-data-table>

    export-title adds Excel / PDF / Cetak buttons (resources/js/plugins/export-buttons.js) that export every filtered row;
    export-message is printed under the title in the file. The "actions" column is never exported.
--}}
@props(['url', 'searchPlaceholder' => 'Cari…', 'filters' => null, 'exportTitle' => null, 'exportMessage' => null])

<div class="datatable" data-datatable data-url="{{ $url }}"
    @if ($exportTitle) data-export-title="{{ $exportTitle }}" data-export-message="{{ $exportMessage }}" @endif>
    <div class="data-toolbar">
        <div class="data-toolbar-left">
            <div class="input-icon">
                <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                <input class="input" type="search" placeholder="{{ $searchPlaceholder }}" data-datatable-search aria-label="Cari">
            </div>
            @if ($exportTitle)
                <div class="data-toolbar-export" data-datatable-export></div>
            @endif
        </div>
        @if ($filters)
            <div class="data-toolbar-right">{{ $filters }}</div>
        @endif
    </div>

    <div class="datatable-scroll">
        <table class="data-table">
            <thead>
                <tr>{{ $slot }}</tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="data-foot">
        <div class="data-foot-info">
            <span data-datatable-info>Memuat data…</span>
            <select class="select" data-datatable-length aria-label="Baris per halaman">
                @foreach ([10, 25, 50, 100] as $length)
                    <option value="{{ $length }}">{{ $length }} per halaman</option>
                @endforeach
            </select>
        </div>
        <div class="pager" data-datatable-pager></div>
    </div>
</div>
