{{-- Per-subject attendance recap. Admin sees every subject, a guru only their own. --}}
@extends('layouts.admin')

@php($isGuru = auth()->user()->isGuru())

@section('title', 'Rekap per Mapel')
@section('breadcrumbs', auth()->user()->role->label().' | Rekap per Mata Pelajaran')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Laporan</span>
        <h1 class="hero-title">Rekap per Mata Pelajaran</h1>
        <p class="hero-sub">Persentase kehadiran tiap siswa pada satu mata pelajaran. {{ $isGuru ? 'Hanya mapel yang Anda ampu.' : 'Semua mapel, termasuk tahun ajaran lama.' }}</p>
    </div>
</section>

@if (! $selectedYear)
    <section class="card">
        <p class="hero-sub" style="margin:0">{{ $isGuru ? 'Anda belum memiliki mata pelajaran di tahun ajaran mana pun.' : 'Belum ada tahun ajaran.' }}</p>
    </section>
@else
    <section class="card">
        <form method="GET" action="{{ route($routeName) }}" class="recap-filters" data-loading="Menyusun rekap mapel…">
            <div class="field">
                <label class="field-label" for="academic_year_id">Tahun ajaran</label>
                <select id="academic_year_id" name="academic_year_id" class="select">
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->academic_year_id }}" @selected($selectedYear->is($year))>{{ $year->year_name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="subject_id">Mata pelajaran</label>
                <select id="subject_id" name="subject_id" class="select" data-searchable>
                    @forelse ($subjects as $subject)
                        <option value="{{ $subject->subject_id }}" @selected($selectedSubject?->is($subject))>{{ $subject->subject_name }} · {{ $subject->schoolClass->class_name }}{{ $isGuru ? '' : ' · '.$subject->teacher->name }}</option>
                    @empty
                        <option value="">Belum ada mapel di tahun ajaran ini</option>
                    @endforelse
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="semester">Periode</label>
                <select id="semester" name="semester" class="select">
                    @foreach ([1, 2] as $semesterNumber)
                        @php([$from, $to] = $selectedYear->semesterRange($semesterNumber))
                        <option value="{{ $semesterNumber }}" @selected((string) $semester === (string) $semesterNumber)>Semester {{ $semesterNumber }} ({{ $from->format('M Y') }} – {{ $to->format('M Y') }})</option>
                    @endforeach
                    <option value="custom" @selected($semester === 'custom')>Rentang tanggal kustom</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="date_from">Dari tanggal</label>
                <input id="date_from" name="date_from" type="date" class="input" value="{{ $dateFrom->toDateString() }}" data-datepicker data-range-start="subject-recap">
            </div>
            <div class="field">
                <label class="field-label" for="date_to">Sampai tanggal</label>
                <input id="date_to" name="date_to" type="date" class="input" value="{{ $dateTo->toDateString() }}" data-datepicker data-range-end="subject-recap">
                @error('date_to') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field recap-filter-actions">
                <button class="btn btn--primary" type="submit">Tampilkan</button>
            </div>
        </form>
    </section>

    <section class="kpi-grid" aria-label="Ringkasan mapel" style="margin-top:20px">
        <article class="kpi-card c-primary">
            <div class="kpi-top"><div class="kpi-identity"><div class="kpi-label">Pertemuan tercatat</div></div></div>
            <div class="kpi-value">{{ number_format($meetings, 0, ',', '.') }}</div>
            <div class="kpi-compare">hari absensi pada periode ini</div>
        </article>
        <article class="kpi-card c-success">
            <div class="kpi-top"><div class="kpi-identity"><div class="kpi-label">Rata-rata kehadiran</div></div></div>
            <div class="kpi-value">{{ number_format($averageAttendance, 1, ',', '.') }}%</div>
            <div class="kpi-compare">{{ number_format($totals['hadir'], 0, ',', '.') }} hadir dari {{ number_format($totals['total'], 0, ',', '.') }} catatan</div>
        </article>
        @foreach ([\App\AttendanceStatus::Permission, \App\AttendanceStatus::Sick, \App\AttendanceStatus::Absent] as $status)
            <article class="kpi-card" data-status="{{ $status->value }}">
                <div class="kpi-top"><div class="kpi-identity"><div class="kpi-label">{{ $status->label() }}</div></div></div>
                <div class="kpi-value">{{ number_format($totals[$status->value], 0, ',', '.') }}</div>
                <div class="kpi-compare">{{ $totals['total'] > 0 ? round($totals[$status->value] / $totals['total'] * 100, 1) : 0 }}% dari seluruh catatan</div>
            </article>
        @endforeach
    </section>

    <section class="card" style="margin-top:20px">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">{{ $selectedYear->year_name }} · {{ $dateFrom->format('d/m/Y') }} – {{ $dateTo->format('d/m/Y') }}</span>
                <h2 class="card-title">{{ $selectedSubject ? $selectedSubject->subject_name.' · '.$selectedSubject->schoolClass->class_name : 'Belum ada mapel' }}</h2>
            </div>
            <div class="card-head-actions">
                <span class="card-action">{{ $rows->count() }} siswa</span>
                <div id="subject-recap-export"></div>
            </div>
        </div>
        <div class="table-scroll">
            <table class="table recap-table" data-testid="subject-recap"
                data-export-table data-export-target="#subject-recap-export"
                data-export-title="Rekap {{ $selectedSubject?->subject_name ?? 'Mapel' }} {{ $selectedSubject?->schoolClass->class_name }}"
                data-export-message="{{ $selectedYear->year_name }} · {{ $dateFrom->format('d/m/Y') }} – {{ $dateTo->format('d/m/Y') }} · {{ $meetings }} pertemuan"
                data-empty-text="Belum ada absensi untuk mapel ini pada periode tersebut.">
                <thead>
                    <tr>
                        <th>NIS</th><th>Nama</th>
                        @foreach (\App\AttendanceStatus::cases() as $status)<th class="num">{{ $status->label() }}</th>@endforeach
                        <th class="num">Total</th><th class="num">% Hadir</th><th>Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($percentage = $row->total > 0 ? round($row->hadir / $row->total * 100, 1) : 0)
                        <tr>
                            <td class="cell-date">{{ $row->nis }}</td>
                            <td class="cell-name">
                                {{ $row->name }}
                                @if ($row->status !== \App\StudentStatus::Active->value)
                                    @include('partials.student-status', ['status' => \App\StudentStatus::from($row->status)])
                                @endif
                            </td>
                            @foreach (\App\AttendanceStatus::cases() as $status)<td class="num">{{ $row->{$status->value} }}</td>@endforeach
                            <td class="num">{{ $row->total }}</td>
                            <td class="num">{{ $percentage }}%</td>
                            <td style="min-width:140px">
                                <div class="progress" title="{{ $percentage }}% hadir">
                                    <div @class(['progress-fill', 'success' => $percentage >= 85, 'warning' => $percentage >= 70 && $percentage < 85, 'danger' => $percentage < 70]) style="width:{{ max($percentage, 2) }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
