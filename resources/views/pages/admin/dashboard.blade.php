@extends('layouts.admin')

@section('title', 'Dashboard Admin')
@section('breadcrumbs', 'Admin | Dashboard')

@php($number = fn (int|float $value): string => number_format($value, is_float($value) ? 1 : 0, ',', '.'))

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Dashboard Admin</span>
        <h1 class="hero-title">Selamat datang, <span class="accent">{{ auth()->user()->name }}</span></h1>
        <p class="hero-sub">Ringkasan data guru, kelas, dan siswa di sekolah.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="{{ route('admin.guru.index') }}#tambah">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Guru
        </a>
        <a class="btn btn--primary" href="{{ route('admin.siswa.index') }}#tambah">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Siswa
        </a>
    </div>
</section>

<section class="kpi-grid" aria-label="Statistik sekolah">
    <article class="kpi-card c-primary" data-stat="teachers">
        <div class="kpi-top">
            <div class="kpi-identity">
                <div class="kpi-icon primary"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg></div>
                <div class="kpi-label">Total guru</div>
            </div>
            <a class="kpi-pill info" href="{{ route('admin.guru.index') }}">Kelola</a>
        </div>
        <div class="kpi-value">{{ $number($stats['teachers']) }}</div>
        <div class="kpi-compare"><strong>{{ $number($stats['activeTeachers']) }}</strong> aktif <span class="sep">·</span> {{ $number($stats['teachers'] - $stats['activeTeachers']) }} nonaktif</div>
    </article>

    <article class="kpi-card c-purple" data-stat="classes">
        <div class="kpi-top">
            <div class="kpi-identity">
                <div class="kpi-icon purple"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></div>
                <div class="kpi-label">Total kelas</div>
            </div>
            <a class="kpi-pill info" href="{{ route('admin.kelas.index') }}">Kelola</a>
        </div>
        <div class="kpi-value">{{ $number($stats['classes']) }}</div>
        <div class="kpi-compare"><strong>{{ $number($stats['emptyClasses']) }}</strong> kelas belum punya siswa</div>
    </article>

    <article class="kpi-card c-success" data-stat="students">
        <div class="kpi-top">
            <div class="kpi-identity">
                <div class="kpi-icon success"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div class="kpi-label">Total siswa</div>
            </div>
            <a class="kpi-pill info" href="{{ route('admin.siswa.index') }}">Kelola</a>
        </div>
        <div class="kpi-value">{{ $number($stats['students']) }}</div>
        <div class="kpi-compare"><strong>{{ $number($stats['maleStudents']) }}</strong> laki-laki <span class="sep">·</span> <strong>{{ $number($stats['femaleStudents']) }}</strong> perempuan <span class="sep">·</span> {{ $number($graduatedStudents) }} lulus</div>
    </article>

    <article class="kpi-card c-danger" data-stat="average">
        <div class="kpi-top">
            <div class="kpi-identity">
                <div class="kpi-icon danger"><svg viewBox="0 0 24 24"><path d="M12 20V10M18 20V4M6 20v-4"/></svg></div>
                <div class="kpi-label">Rata-rata per kelas</div>
            </div>
        </div>
        <div class="kpi-value">{{ $number($stats['averagePerClass']) }}</div>
        <div class="kpi-compare">siswa per kelas <span class="sep">·</span> {{ $number($stats['students']) }} siswa di {{ $number($stats['classes']) }} kelas</div>
    </article>
</section>

<section class="card" style="margin-bottom:20px" data-section="academic-year">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Tahun ajaran aktif</span>
            <h2 class="card-title">{{ $academicYear?->year_name ?? "Belum ada tahun ajaran" }}</h2>
        </div>
        <a class="card-action" href="{{ route('admin.tahun-ajaran.index') }}{{ $academicYear ? '' : '#tambah' }}">{{ $academicYear ? "Kelola" : "Buat tahun ajaran" }} <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
    </div>
    @if ($academicYear)
        @include("partials.year-progress", ["academicYear" => $academicYear, "progress" => $yearProgress])
    @else
        <p class="hero-sub" style="margin:0">Mulai operasi tahunan dengan membuat tahun ajaran.</p>
    @endif
</section>

<div class="grid" style="margin-bottom:20px">
    <section class="col-6 card" data-section="class-chart">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Distribusi</span><h2 class="card-title">Siswa aktif per kelas</h2></div>
            <a class="card-action" href="{{ route('admin.kelas.index') }}">Data kelas <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
        </div>
        @if ($classes->isEmpty())
            <p class="hero-sub">Belum ada kelas.</p>
        @else
            <div class="chart-box">
                <canvas data-chart data-chart-source="class-chart-data" role="img" aria-label="Grafik batang jumlah siswa aktif per kelas"></canvas>
            </div>
            <script type="application/json" id="class-chart-data">@json($classChart)</script>
            {{-- Text alternative for screen readers and when JavaScript is unavailable. --}}
            <table class="sr-only">
                <caption>Siswa aktif per kelas</caption>
                <tbody>
                    @foreach ($classes as $schoolClass)
                        <tr><th>{{ $schoolClass->class_name }}</th><td>{{ $schoolClass->students_count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="col-6 card" data-section="attendance-chart">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Kehadiran mingguan{{ $attendanceChart ? ' · '.$attendanceChart['from'].' – '.$attendanceChart['to'] : '' }}</span>
                <h2 class="card-title">Absensi {{ \App\Http\Controllers\Admin\DashboardController::ATTENDANCE_CHART_WEEKS }} minggu terakhir</h2>
            </div>
            <a class="card-action" href="{{ route('admin.laporan.index') }}">Rekap <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
        </div>
        @if (! $attendanceChart)
            <p class="hero-sub">Belum ada data absensi.</p>
        @else
            <div class="chart-box">
                <canvas data-chart data-chart-source="attendance-chart-data" role="img" aria-label="Grafik batang bertumpuk jumlah absensi per status per minggu"></canvas>
            </div>
            <script type="application/json" id="attendance-chart-data">@json($attendanceChart)</script>
            <div class="field-help" style="margin-top:8px">Minggu dimulai Senin; {{ number_format($attendanceChart['total'], 0, ',', '.') }} catatan. Delapan minggu terakhir yang memiliki data absensi.</div>
            <table class="sr-only">
                <caption>Absensi per minggu</caption>
                <thead><tr><th>Minggu</th>@foreach ($attendanceChart['datasets'] as $dataset)<th>{{ $dataset['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($attendanceChart['labels'] as $index => $label)
                        <tr><th>{{ $label }}</th>@foreach ($attendanceChart['datasets'] as $dataset)<td>{{ $dataset['data'][$index] }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>

<div class="grid">
    <section class="col-6 card">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Terbaru</span><h2 class="card-title">Siswa baru ditambahkan</h2></div>
            <a class="card-action" href="{{ route('admin.siswa.index') }}">Semua siswa <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th></tr></thead>
                <tbody>
                    @forelse ($recentStudents as $student)
                        <tr>
                            <td class="cell-date">{{ $student->nis }}</td>
                            <td class="cell-name">{{ $student->name }}</td>
                            <td>{{ $student->schoolClass->class_name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="cell-date">Belum ada siswa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="col-6 card">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Akun</span><h2 class="card-title">Guru terbaru</h2></div>
            <a class="card-action" href="{{ route('admin.guru.index') }}">Data guru <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Nama</th><th>Email</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($recentTeachers as $teacher)
                        <tr>
                            <td class="cell-name">{{ $teacher->name }}</td>
                            <td class="cell-date">{{ $teacher->email }}</td>
                            <td>@include('partials.status-badge', ['isActive' => $teacher->is_active])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="cell-date">Belum ada akun guru.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
