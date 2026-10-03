{{-- Row actions. Expects $attendance, $routes, $canUpdate, $canDelete. Edit opens the page's attendance modal. --}}
<div class="data-cell-actions">
    @if ($canUpdate)
        <button type="button" class="btn--icon" data-modal-open="attendance-modal" data-modal-url="{{ route($routes['edit'], $attendance) }}"
            aria-label="Edit" title="{{ auth()->user()->isAdmin() && ! $attendance->isToday() ? 'Koreksi' : 'Edit' }}">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
        </button>
    @else
        <span class="cell-date" title="Absensi tanggal lampau hanya bisa dikoreksi admin">Terkunci</span>
    @endif
    @if ($canDelete)
        <form method="POST" action="{{ route($routes['destroy'], $attendance) }}" data-ajax data-confirm="Hapus absensi {{ $attendance->student_name }} tanggal {{ $attendance->date->format('d/m/Y') }}?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn--icon is-danger is-delete" aria-label="Hapus" title="Hapus">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
        </form>
    @endif
</div>
