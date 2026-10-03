{{-- Row action for one academic year. Only an empty, inactive year can be deleted; the rest is history. --}}
@if ($year->is_active)
    <span class="cell-date" title="Tahun ajaran aktif tidak bisa dihapus">Aktif</span>
@elseif ($year->homeroom_teachers_count > 0 || $year->subjects_count > 0 || $year->promotions_count > 0 || $year->attendances_count > 0)
    <span class="cell-date" title="Tahun ajaran dengan data tersimpan sebagai histori">Histori</span>
@else
    <div class="data-cell-actions">
        <form method="POST" action="{{ route('admin.tahun-ajaran.destroy', $year) }}" data-ajax
            data-confirm="Hapus tahun ajaran {{ $year->year_name }}? Tahun ajaran ini belum memiliki data.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn--icon is-danger is-delete" aria-label="Hapus" title="Hapus">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
        </form>
    </div>
@endif
