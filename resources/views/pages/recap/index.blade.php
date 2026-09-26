{{-- Shared by admin (all data) and guru (own subjects only). Expects $routeName and the filter/result data. --}}
@extends('layouts.admin')

@php($isGuru = auth()->user()->isGuru())
@php($pageTitle = $isGuru ? 'Rekap Absensi Mapel' : 'Rekap Absensi')

@section('title', $pageTitle)
@section('breadcrumbs', auth()->user()->role->label().' | '.$pageTitle)

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Laporan</span>
        <h1 class="hero-title">{{ $pageTitle }}</h1>
        <p class="hero-sub">{{ $isGuru ? 'Hanya menampilkan absensi mata pelajaran yang Anda ampu.' : 'Rekap absensi seluruh kelas dan mata pelajaran.' }} Kelas mengikuti kelas mapel pada tahun ajaran tersebut, jadi histori tetap benar setelah kenaikan kelas.</p>
    </div>
</section>

@if (! $selectedYear)
    <section class="card">
        <p class="hero-sub" style="margin:0">{{ $isGuru ? 'Anda belum memiliki mata pelajaran di tahun ajaran mana pun.' : 'Belum ada tahun ajaran.' }}</p>
    </section>
@else
    <section class="card">
        <form method="GET" action="{{ route($routeName) }}" class="recap-filters" data-loading="Menyusun rekap absensi…">
            <div class="field">
                <label class="field-label" for="academic_year_id">Tahun ajaran</label>
                <select id="academic_year_id" name="academic_year_id" class="select">
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->academic_year_id }}" @selected($selectedYear->is($year))>{{ $year->year_name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
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
                <input id="date_from" name="date_from" type="date" class="input" value="{{ $dateFrom->toDateString() }}" data-datepicker data-range-start="recap">
            </div>
            <div class="field">
                <label class="field-label" for="date_to">Sampai tanggal</label>
                <input id="date_to" name="date_to" type="date" class="input" value="{{ $dateTo->toDateString() }}" data-datepicker data-range-end="recap">
                @error('date_to') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label" for="class_id">Kelas</label>
                <select id="class_id" name="class_id" class="select" data-searchable>
                    <option value="">Semua kelas</option>
                    @foreach ($classes as $schoolClass)
                        <option value="{{ $schoolClass->class_id }}" @selected($selectedClass?->is($schoolClass))>{{ $schoolClass->class_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="subject_id">Mata pelajaran</label>
                <select id="subject_id" name="subject_id" class="select" data-searchable>
                    <option value="">Semua mapel</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->subject_id }}" @selected($selectedSubject?->is($subject))>{{ $subject->subject_name }} · {{ $subject->schoolClass->class_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field recap-filter-actions">
                <button class="btn btn--primary" type="submit">Tampilkan</button>
            </div>
        </form>
        <div class="field-help" style="margin-top:8px">Tanggal kustom dipakai jika periode "Rentang tanggal kustom" dipilih.</div>
    </section>

    <section class="kpi-grid" aria-label="Ringkasan absensi" style="margin-top:20px">
        @foreach (\App\AttendanceStatus::cases() as $status)
            <article class="kpi-card" data-status="{{ $status->value }}">
                <div class="kpi-top"><div class="kpi-identity"><div class="kpi-label">{{ $status->label() }}</div></div></div>
                <div class="kpi-value">{{ number_format($totals[$status->value], 0, ',', '.') }}</div>
                <div class="kpi-compare">{{ $totals['total'] > 0 ? round($totals[$status->value] / $totals['total'] * 100, 1) : 0 }}% dari {{ number_format($totals['total'], 0, ',', '.') }} catatan</div>
            </article>
        @endforeach
    </section>

    <section class="card" style="margin-top:20px">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">{{ $selectedYear->year_name }} · {{ $dateFrom->format('d/m/Y') }} – {{ $dateTo->format('d/m/Y') }}</span>
                <h2 class="card-title">{{ $selectedClass?->class_name ?? 'Semua kelas' }}{{ $selectedSubject ? ' · '.$selectedSubject->subject_name : '' }}</h2>
            </div>
            <div class="card-head-actions">
                <span class="card-action">{{ $rows->count() }} siswa</span>
                <div id="recap-export"></div>
            </div>
        </div>
        <div class="table-scroll">
            @php($recapScope = ($selectedClass?->class_name ?? 'Semua kelas').($selectedSubject ? ' · '.$selectedSubject->subject_name : ''))
            <table class="table recap-table" data-testid="recap"
                data-export-table data-export-target="#recap-export"
                data-export-title="{{ $pageTitle }} {{ $selectedYear->year_name }}"
                data-export-message="Periode {{ $dateFrom->format('d/m/Y') }} – {{ $dateTo->format('d/m/Y') }} · {{ $recapScope }} · {{ $totals['total'] }} catatan"
                data-empty-text="Tidak ada data absensi untuk filter ini.">
                <thead>
                    <tr>
                        <th>NIS</th><th>Nama</th><th>Kelas</th>
                        @foreach (\App\AttendanceStatus::cases() as $status)<th class="num">{{ $status->label() }}</th>@endforeach
                        <th class="num">Total</th><th class="num">% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="cell-date">{{ $row->nis }}</td>
                            <td class="cell-name">{{ $row->name }} @if ($row->status === 'lulus')<span class="badge purple">Lulus</span>@endif</td>
                            <td>{{ $row->class_name }}</td>
                            @foreach (\App\AttendanceStatus::cases() as $status)<td class="num">{{ $row->{$status->value} }}</td>@endforeach
                            <td class="num">{{ $row->total }}</td>
                            <td class="num">{{ $row->total > 0 ? round($row->hadir / $row->total * 100, 1) : 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
