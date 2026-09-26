{{-- Card body for the active academic year and its yearly-flow checklist. Expects $current and $progress (null when there is no year). --}}
<div class="card-head">
    <div class="card-title-wrap">
        <span class="eyebrow">Tahun ajaran aktif</span>
        <h2 class="card-title">{{ $current?->year_name ?? 'Belum ada' }}</h2>
    </div>
</div>
@if ($current)
    @include('partials.year-progress', ['academicYear' => $current, 'progress' => $progress])
@else
    <p class="hero-sub" style="margin:0">Belum ada tahun ajaran. Buat tahun ajaran pertama untuk memulai.</p>
@endif
