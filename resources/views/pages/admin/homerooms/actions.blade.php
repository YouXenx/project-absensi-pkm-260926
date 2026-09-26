{{-- Row actions for one class. Expects $schoolClass (with homeroom_id of the listed year) and $isEditable (listed year is active). --}}
@if (! $isEditable)
    <span class="badge">Histori</span>
@elseif ($schoolClass->homeroom_id)
    @include('partials.datatable-actions', [
        'modal' => 'homeroom-modal',
        'editUrl' => route('admin.wali-kelas.edit', $schoolClass->homeroom_id),
        'deleteUrl' => route('admin.wali-kelas.destroy', $schoolClass->homeroom_id),
        'deleteMessage' => "Hapus penugasan wali {$schoolClass->class_name}?",
    ])
@else
    <button type="button" class="btn btn--ghost" style="padding:5px 10px;font-size:12px"
        data-modal-open="homeroom-modal" data-modal-create data-modal-url="{{ route('admin.wali-kelas.create', ['class_id' => $schoolClass->class_id]) }}">Tetapkan</button>
@endif
