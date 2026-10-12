{{--
    "Impor Excel" button of a master data page, with the dialog that takes the file.
    <x-import-excel type="siswa" />   type: guru | kelas | siswa | wali-kelas | mapel (App\Imports\MasterImport::TYPES)
    The upload goes to a preview page; nothing is saved before the admin confirms there.
--}}
@props(['type'])

@php($import = \App\Imports\MasterImport::for($type))
@php($failed = $errors->has('file') && old('_import') === $type)

<button type="button" class="btn btn--ghost" data-dialog-open="import-{{ $type }}">
    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/></svg> Impor Excel
</button>

<dialog id="import-{{ $type }}" class="app-dialog" data-dialog aria-labelledby="import-{{ $type }}-title" @if ($failed) data-dialog-autoopen @endif>
    <header class="app-dialog-head">
        <div class="app-dialog-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13l4 5M12 13l-4 5"/></svg>
        </div>
        <div class="app-dialog-heading">
            <h2 class="app-dialog-title" id="import-{{ $type }}-title">Impor {{ $import->title() }}</h2>
            <div class="app-dialog-sub">Dari file Excel</div>
        </div>
        <button type="button" class="app-dialog-close" data-dialog-close aria-label="Tutup" title="Tutup">
            <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </header>

    <form method="POST" action="{{ route('admin.impor.preview', $type) }}" enctype="multipart/form-data" class="app-dialog-body import-form" data-loading="Memeriksa isi file Excel…">
        @csrf
        <input type="hidden" name="_import" value="{{ $type }}">

        <p class="import-help">
            Untuk menambah banyak {{ $import->noun() }} sekaligus. Kolom template:
            {!! collect($import->headers())->map(fn (string $heading): string => '<strong>'.e($heading).'</strong>')->implode(', ') !!}.
            Isi file diperiksa dan ditampilkan sebagai preview dahulu, lalu data baru tersimpan setelah Anda konfirmasi.
        </p>

        <a class="btn btn--ghost import-template" href="{{ route('admin.impor.template', $type) }}">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> Download Template Excel
        </a>

        <div class="field">
            <label class="field-label" for="import-{{ $type }}-file">File Excel <span class="req">*</span></label>
            <input id="import-{{ $type }}-file" name="file" type="file" accept=".xlsx,.xls,.csv" required data-file-upload @class(['input', 'is-invalid' => $failed])>
            <div class="field-help">Format .xlsx, .xls, atau .csv. Maksimal 2 MB dan {{ number_format(\App\Imports\MasterImport::MAX_ROWS, 0, ',', '.') }} baris.</div>
            @if ($failed)
                <div class="field-error">{{ $errors->first('file') }}</div>
            @endif
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn--ghost" data-dialog-close>Batal</button>
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Periksa &amp; Preview</button>
        </div>
    </form>
</dialog>
