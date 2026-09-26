{{-- Expects $academicYear, $subjects (own, active year, open for attendance), $lockedSubjectCount, $subject, $students (active roster), $recorded (today's records keyed by student_id), $today. --}}
@extends('layouts.admin')

@section('title', 'Absensi Siswa')
@section('breadcrumbs', 'Guru | Absensi Siswa')

@php($statuses = \App\AttendanceStatus::cases())

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Operasi harian · {{ $today->translatedFormat('l') }}, {{ $today->format('d/m/Y') }}</span>
        <h1 class="hero-title">Absensi Siswa</h1>
        <p class="hero-sub">Pilih mata pelajaran, lalu isi kehadiran per siswa atau sekaligus. Absensi hanya bisa diisi dan diubah untuk hari ini.</p>
    </div>
</section>

<section class="card">
    <form method="GET" action="{{ route('guru.absensi.index') }}" class="data-toolbar" style="margin-bottom:0" data-loading="Memuat daftar siswa…">
        <div class="data-toolbar-left">
            <label class="field-label" for="subject_id">Mata pelajaran</label>
            <select id="subject_id" name="subject_id" class="select" style="max-width:420px" data-auto-submit data-searchable data-searchable-width="420px">
                <option value="">{{ $subjects->isEmpty() ? 'Belum ada mapel di tahun ajaran aktif' : 'Pilih mata pelajaran' }}</option>
                @foreach ($subjects as $option)
                    <option value="{{ $option->subject_id }}" @selected($subject?->is($option))>{{ $option->subject_name }} · {{ $option->schoolClass->class_name }}</option>
                @endforeach
            </select>
        </div>
    </form>
    @if ($lockedSubjectCount > 0)
        <div class="field-help" style="margin-top:8px" data-testid="locked-subjects">
            {{ $lockedSubjectCount }} mapel Anda belum dibuka untuk absensi karena persiapan tahun ajaran (kenaikan kelas / wali kelas) belum selesai. Lihat <a href="{{ route('guru.mapel.index') }}">Mapel yang Diampu</a>.
        </div>
    @endif
</section>

@if ($subject)
    <section class="card" style="margin-top:20px">
        <form method="POST" action="{{ route('guru.absensi.store', $subject) }}" novalidate data-attendance-form data-loading="Menyimpan absensi…">
            @csrf
            <input type="hidden" name="date" value="{{ $today->toDateString() }}">

            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">{{ $subject->schoolClass->class_name }} · {{ $students->count() }} siswa aktif</span>
                    <h2 class="card-title">{{ $subject->subject_name }}</h2>
                </div>
                <div class="card-head-actions">
                    <div class="attendance-counters" data-attendance-counters aria-live="polite">
                        @foreach ($statuses as $status)
                            <span class="attendance-counter" data-status="{{ $status->value }}">
                                <span class="attendance-counter-label">{{ $status->label() }}</span>
                                <strong data-count="{{ $status->value }}">0</strong>
                            </span>
                        @endforeach
                        <span class="attendance-counter is-empty">
                            <span class="attendance-counter-label">Belum diisi</span>
                            <strong data-count="empty">{{ $students->count() }}</strong>
                        </span>
                    </div>
                </div>
            </div>

            @error('date')
                <div class="alert danger" style="margin-bottom:14px"><span class="ico">!</span><div class="body">{{ $message }}</div></div>
            @enderror

            @if ($students->isEmpty())
                <p class="hero-sub" style="margin:0">Tidak ada siswa aktif di kelas ini.</p>
            @else
                <div class="attendance-toolbar">
                    <div class="input-icon attendance-search">
                        <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                        <input class="input" type="search" placeholder="Cari nama atau NIS siswa…" data-attendance-filter aria-label="Cari siswa di daftar absensi">
                    </div>
                    <span class="spacer"></span>
                    <button type="button" class="btn btn--ghost" data-mark-all="hadir">
                        <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Tandai semua hadir
                    </button>
                </div>

                <div class="table-scroll attendance-scroll">
                    <table class="table attendance-table" data-testid="attendance-roster">
                        <thead>
                            <tr><th style="width:110px">NIS</th><th>Nama</th><th style="width:330px">Kehadiran</th><th>Keterangan</th><th style="width:90px"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php($record = $recorded->get($student->student_id))
                                @php($selected = old("attendance.{$student->student_id}.status", $record?->status->value))
                                <tr data-attendance-row data-student="{{ $student->nis }} {{ $student->name }}" data-status="{{ $selected }}" @class(['is-recorded' => $record])>
                                    <td class="cell-date">{{ $student->nis }}</td>
                                    <td class="cell-name">
                                        {{ $student->name }}
                                        @if ($record)
                                            <div class="field-help">Tersimpan {{ $record->updated_at->format('H:i') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="status-pills" role="radiogroup" aria-label="Kehadiran {{ $student->name }}">
                                            @foreach ($statuses as $status)
                                                <label class="status-pill" data-status="{{ $status->value }}">
                                                    <input type="radio" name="attendance[{{ $student->student_id }}][status]" value="{{ $status->value }}" @checked($selected === $status->value)>
                                                    <span>{{ $status->label() }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error("attendance.{$student->student_id}.status")
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </td>
                                    <td>
                                        <input class="input" type="text" maxlength="255" placeholder="Opsional"
                                            name="attendance[{{ $student->student_id }}][description]"
                                            value="{{ old("attendance.{$student->student_id}.description", $record?->description) }}">
                                    </td>
                                    <td class="text-end">
                                        <button type="submit" class="btn btn--ghost attendance-row-save"
                                            formaction="{{ route('guru.absensi.store-one', [$subject, $student]) }}">Simpan</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="hero-sub attendance-empty" data-attendance-empty hidden>Tidak ada siswa yang cocok dengan pencarian.</p>
                </div>

                <div class="form-actions attendance-actions">
                    <span class="field-help">"Simpan" per baris menyimpan satu siswa. "Simpan semua" membutuhkan status untuk semua siswa.</span>
                    <span class="spacer"></span>
                    <button type="submit" class="btn btn--primary">Simpan semua</button>
                </div>
            @endif
        </form>
    </section>
@endif
@endsection
