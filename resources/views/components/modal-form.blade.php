{{--
    Create/edit form in a modal, driven by resources/js/modal.js. Every module uses this one structure;
    the slot only holds that module's fields (inside .form-grid).

    <x-modal-form id="class-modal" title="Tambah Kelas" edit-title="Edit Kelas" :action="route('admin.kelas.store')">
        <div class="field span-2">
            <label class="field-label" for="class-modal-class_name">Nama Kelas</label>
            <input id="class-modal-class_name" name="class_name" class="input">
        </div>
    </x-modal-form>

    Open it with any element:
        <button type="button" data-modal-open="class-modal">Tambah</button>                          (empty create form)
        <button type="button" data-modal-open="class-modal" data-modal-url="{{ route(...edit) }}">     (fetches the payload first)

    Payload (JSON from the create/edit endpoint): action, method, title, confirm, values {field: value}, slots {name: html}.
    Inside the slot:
        data-modal-show="create|edit"  element only shown (and its inputs only submitted) in that mode
        data-modal-slot="name"          innerHTML replaced by payload.slots.name (e.g. <option>s that depend on current data)
        data-modal-text="key"           textContent set from payload.values.key
--}}
@props([
    'id',
    'title',
    'editTitle' => null,
    'action',
    'method' => 'POST',
    'submitLabel' => 'Simpan',
    'size' => null,
])

<div class="modal-backdrop" id="{{ $id }}" data-modal-form hidden
    data-title-create="{{ $title }}" data-title-edit="{{ $editTitle ?? $title }}"
    data-action-create="{{ $action }}" data-method-create="{{ $method }}">
    <div @class(['modal-demo', 'modal-panel', 'modal-lg' => $size === 'lg']) role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <form method="POST" action="{{ $action }}" novalidate>
            @csrf
            <input type="hidden" name="_method" value="{{ $method }}" data-modal-method>

            <div class="modal-head">
                <div class="modal-title" id="{{ $id }}-title" data-modal-title>{{ $title }}</div>
                <button type="button" class="mail-tool" data-modal-close aria-label="Tutup">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                </button>
            </div>

            <div class="modal-body">
                <div class="alert danger" data-modal-alert hidden>
                    <span class="ico">!</span>
                    <div class="body" data-modal-alert-text></div>
                </div>
                <div class="modal-loading" data-modal-loading hidden><span class="spinner"></span> Memuat data…</div>
                <div class="modal-busy" data-modal-busy hidden><span class="spinner"></span> Menyimpan…</div>
                <div class="form-grid" data-modal-fields>
                    {{ $slot }}
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="submit" class="btn btn--primary" data-modal-submit>{{ $submitLabel }}</button>
            </div>
        </form>
    </div>
</div>
