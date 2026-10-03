{{-- Row actions for a guru account: edit (modal), reset password, activate/deactivate and delete (Ajax).
     Delete only goes through for an account without mapel/wali kelas; the controller explains the alternative. --}}
<div class="data-cell-actions">
    <button type="button" class="btn--icon is-edit" data-modal-open="teacher-modal" data-modal-url="{{ route('admin.guru.edit', $teacher) }}" aria-label="Edit" title="Edit">
        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
    </button>
    <form method="POST" action="{{ route('admin.guru.reset-password', $teacher) }}" data-ajax
        data-confirm="Reset password {{ $teacher->name }}? Password lama tidak bisa dipakai lagi.">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn--icon" aria-label="Reset password" title="Reset password">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
        </button>
    </form>
    <form method="POST" action="{{ route('admin.guru.status', $teacher) }}" data-ajax
        data-confirm="{{ $teacher->is_active ? "Nonaktifkan akun {$teacher->name}? Guru ini tidak akan bisa login." : "Aktifkan kembali akun {$teacher->name}?" }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="is_active" value="{{ $teacher->is_active ? 0 : 1 }}">
        @if ($teacher->is_active)
            <button type="submit" class="btn--icon is-danger" aria-label="Nonaktifkan" title="Nonaktifkan">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.7 5.7l12.6 12.6"/></svg>
            </button>
        @else
            <button type="submit" class="btn--icon" aria-label="Aktifkan" title="Aktifkan">
                <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
            </button>
        @endif
    </form>
    <form method="POST" action="{{ route('admin.guru.destroy', $teacher) }}" data-ajax
        data-confirm="Hapus akun {{ $teacher->name }}? Akun yang sudah punya mapel atau kelas tidak bisa dihapus — nonaktifkan saja.">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn--icon is-danger is-delete" aria-label="Hapus" title="Hapus akun">
            <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        </button>
    </form>
</div>
