{{-- Yearly-flow checklist. Expects $academicYear and $progress (AcademicYear::setupProgress()). --}}
@php
    $stepRoutes = ['promotion' => 'admin.kenaikan.index', 'homeroom' => 'admin.wali-kelas.index', 'subjects' => 'admin.mapel.index'];
@endphp

<ol class="year-steps">
    <li class="year-step is-done">
        <span class="year-step-mark">1</span>
        <div>
            <div class="year-step-title">Tahun ajaran {{ $academicYear->year_name }} dibuat</div>
            <div class="field-help">Aktif sejak {{ $academicYear->created_at?->format('d/m/Y') }}.</div>
        </div>
    </li>
    @foreach ($progress['steps'] as $step)
        <li @class(['year-step', 'is-done' => $step['done']])>
            <span class="year-step-mark">{{ $loop->iteration + 1 }}</span>
            <div>
                <div class="year-step-title">
                    <a href="{{ route($stepRoutes[$step['key']]) }}">{{ $step['label'] }}</a>
                    @if ($step['done'])
                        <span class="badge success dot">Selesai</span>
                    @else
                        <span class="badge warning dot">Belum selesai</span>
                    @endif
                </div>
                <div class="field-help">{{ $step['detail'] }}</div>
            </div>
        </li>
    @endforeach
    <li @class(['year-step', 'is-done' => $progress['complete']])>
        <span class="year-step-mark">5</span>
        <div>
            <div class="year-step-title">Siswa siap diabsen</div>
            <div class="field-help">{{ $progress['complete'] ? 'Semua langkah selesai.' : 'Selesaikan langkah di atas terlebih dahulu.' }}</div>
        </div>
    </li>
</ol>
