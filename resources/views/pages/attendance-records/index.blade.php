{{-- Guru "Riwayat Absensi" (own subjects, one date) and admin "Koreksi Absensi" (all data, date range). --}}
@extends('layouts.admin')

@php($isAdmin = auth()->user()->isAdmin())
@php($pageTitle = $isAdmin ? 'Koreksi Absensi' : 'Riwayat Absensi')
@php($query = request()->only(['academic_year_id', 'class_id', 'subject_id', 'status', 'date', 'date_from', 'date_to']))

@section('title', $pageTitle)
@section('breadcrumbs', auth()->user()->role->label().' | '.$pageTitle)

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">{{ $isAdmin ? 'Operasi insidental' : 'Operasi harian' }}</span>
        <h1 class="hero-title">{{ $pageTitle }}</h1>
        <p class="hero-sub">
            @if ($isAdmin)
                Semua catatan absensi. Admin dapat mengoreksi atau menghapus absensi tanggal berapa pun.
            @else
                Absensi mata pelajaran yang Anda ampu per tanggal. Hanya absensi hari ini yang bisa diubah; tanggal lampau dikoreksi oleh admin.
            @endif
        </p>
    </div>
</section>

<section class="card">
    <form method="GET" action="{{ route($routes['index']) }}" class="recap-filters" data-loading="Memuat data absensi…">
        @if ($isAdmin)
            <div class="field">
                <label class="field-label" for="academic_year_id">Tahun ajaran</label>
                <select id="academic_year_id" name="academic_year_id" class="select">
                    <option value="">Semua</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->academic_year_id }}" @selected($filters['academic_year_id'] === $year->academic_year_id)>{{ $year->year_name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="class_id">Kelas</label>
                <select id="class_id" name="class_id" class="select" data-searchable>
                    <option value="">Semua kelas</option>
                    @foreach ($classes as $schoolClass)
                        <option value="{{ $schoolClass->class_id }}" @selected($filters['class_id'] === $schoolClass->class_id)>{{ $schoolClass->class_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="field">
            <label class="field-label" for="subject_id">Mata pelajaran</label>
            <select id="subject_id" name="subject_id" class="select" data-searchable>
                <option value="">Semua mapel</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->subject_id }}" @selected($filters['subject_id'] === $subject->subject_id)>
                        {{ $subject->subject_name }} · {{ $subject->schoolClass->class_name }}{{ $subject->academicYear->is_active ? '' : ' ('.$subject->academicYear->year_name.')' }}
                    </option>
                @endforeach
            </select>
        </div>

        @if ($isAdmin)
            <div class="field">
                <label class="field-label" for="date_from">Dari tanggal</label>
                <input id="date_from" name="date_from" type="date" class="input" value="{{ $filters['date_from']->toDateString() }}" data-datepicker data-range-start="records">
            </div>
            <div class="field">
                <label class="field-label" for="date_to">Sampai tanggal</label>
                <input id="date_to" name="date_to" type="date" class="input" value="{{ $filters['date_to']->toDateString() }}" data-datepicker data-range-end="records">
            </div>
        @else
            <div class="field">
                <label class="field-label" for="date">Tanggal</label>
                <input id="date" name="date" type="date" class="input" value="{{ $filters['date_from']->toDateString() }}" max="{{ now()->toDateString() }}" data-datepicker>
            </div>
        @endif

        <div class="field">
            <label class="field-label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">Semua status</option>
                @foreach (\App\AttendanceStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="field recap-filter-actions">
            <button class="btn btn--primary" type="submit">Tampilkan</button>
        </div>
    </form>
</section>

<section class="kpi-grid" aria-label="Ringkasan" style="margin-top:20px" data-fragment="attendance-summary">
    @include('pages.attendance-records.summary')
</section>

<section class="card" style="margin-top:20px">
    <x-data-table :url="route($routes['data'], $query)" search-placeholder="Cari NIS, nama siswa, mapel, atau kelas…"
        :export-title="$pageTitle"
        :export-message="'Periode '.$filters['date_from']->format('d/m/Y').($filters['date_from']->equalTo($filters['date_to']) ? '' : ' – '.$filters['date_to']->format('d/m/Y'))">
        <th data-data="date">Tanggal</th>
        <th data-data="subject">Mapel · Kelas</th>
        <th data-data="nis">NIS</th>
        <th data-data="student_name">Nama</th>
        <th data-data="status">Status</th>
        <th data-data="description" data-orderable="false">Keterangan</th>
        <th data-data="actions" data-orderable="false" data-class="text-end" style="width:90px"></th>
    </x-data-table>
</section>

<x-modal-form id="attendance-modal" title="Edit Absensi" :action="route($routes['index'])" method="PUT">
    <div class="field span-2">
        <span class="eyebrow" data-modal-text="context"></span>
        <p class="modal-note" style="margin:4px 0 0" data-modal-text="note"></p>
    </div>
    <div class="field span-2">
        <span class="field-label">Kehadiran <span class="req">*</span></span>
        <div class="status-radios">
            @foreach (\App\AttendanceStatus::cases() as $status)
                <label class="check radio">
                    <input type="radio" name="status" value="{{ $status->value }}">
                    <span class="box"></span> {{ $status->label() }}
                </label>
            @endforeach
        </div>
    </div>
    <div class="field span-2">
        <label class="field-label" for="attendance-modal-description">Keterangan</label>
        <input id="attendance-modal-description" name="description" type="text" maxlength="255" class="input">
    </div>
</x-modal-form>
@endsection
