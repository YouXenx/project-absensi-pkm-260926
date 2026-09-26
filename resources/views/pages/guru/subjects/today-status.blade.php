{{-- Today's attendance progress for a subject. Expects $recorded, $expected and $lockedReason (null when attendance is open). --}}
@if ($lockedReason ?? null)
    <span class="badge dot" title="{{ $lockedReason }}">Belum dibuka</span>
    <div class="field-help">{{ $lockedReason }}</div>
@elseif ($expected === 0)
    <span class="cell-date">Tidak ada siswa aktif</span>
@elseif ($recorded === 0)
    <span class="badge warning dot">Belum diabsen</span>
@elseif ($recorded < $expected)
    <span class="badge info dot">{{ $recorded }}/{{ $expected }} siswa</span>
@else
    <span class="badge success dot">Selesai ({{ $recorded }})</span>
@endif
