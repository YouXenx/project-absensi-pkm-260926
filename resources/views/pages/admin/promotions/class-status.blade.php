{{-- Promotion state of one class in the active year. Expects $schoolClass with active_students_count,
     pending_students_count and processed_students_count. --}}
@php
    $state = match (true) {
        $schoolClass->processed_students_count > 0 && $schoolClass->pending_students_count === 0 => ['success dot', 'Selesai'],
        $schoolClass->processed_students_count > 0 => ['warning dot', 'Sebagian'],
        $schoolClass->active_students_count === 0 => ['', 'Tidak ada siswa'],
        default => ['info dot', 'Belum diproses'],
    };
@endphp
<span class="badge {{ $state[0] }}">{{ $state[1] }}</span>
