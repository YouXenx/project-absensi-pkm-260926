@extends('layouts.admin')

@section('title', 'Mata Pelajaran')
@section('breadcrumbs', 'Tahun Ajaran | Mata Pelajaran')

@php($requestedYearId = (int) request('academic_year_id') ?: $activeYear?->academic_year_id)

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Langkah 4</span>
        <h1 class="hero-title">Mata Pelajaran</h1>
        <p class="hero-sub">Penugasan mapel per guru per kelas. Mapel baru otomatis masuk tahun ajaran aktif{{ $activeYear ? " ({$activeYear->year_name})" : '' }}; mapel tahun lama tampil sebagai histori.</p>
    </div>
    @if ($activeYear)
        <div class="hero-actions">
            <button type="button" class="btn btn--primary" data-modal-open="subject-modal" data-modal-url="{{ route('admin.mapel.create') }}" data-modal-create data-modal-hash="tambah">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Tambah Mapel
            </button>
        </div>
    @endif
</section>

<section class="card">
    <x-data-table :url="route('admin.mapel.data')" search-placeholder="Cari mapel, kelas, atau guru…" export-title="Mata Pelajaran">
        <x-slot:filters>
            <select class="select" data-datatable-filter="academic_year_id" aria-label="Filter tahun ajaran">
                @foreach ($academicYears as $year)
                    <option value="{{ $year->academic_year_id }}" @selected($year->academic_year_id === $requestedYearId)>{{ $year->year_name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                @endforeach
            </select>
            <select class="select" data-datatable-filter="class_id" aria-label="Filter kelas" data-searchable data-searchable-width="160px">
                <option value="">Semua kelas</option>
                @foreach ($classes as $schoolClass)
                    <option value="{{ $schoolClass->class_id }}">{{ $schoolClass->class_name }}</option>
                @endforeach
            </select>
            <select class="select" data-datatable-filter="user_id" aria-label="Filter guru" data-searchable data-searchable-width="200px">
                <option value="">Semua guru</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
        </x-slot:filters>

        <th data-data="subject_name">Mata Pelajaran</th>
        <th data-data="class_name">Kelas</th>
        <th data-data="teacher_name">Guru Pengampu</th>
        <th data-data="attendances_count">Data Absensi</th>
        <th data-data="actions" data-orderable="false" data-class="text-end" class="text-end">Aksi</th>
    </x-data-table>
</section>

@if ($activeYear)
    <x-modal-form id="subject-modal" title="Tambah Mata Pelajaran" edit-title="Edit Mata Pelajaran" :action="route('admin.mapel.store')" size="lg">
        <div class="field">
            <label class="field-label" for="subject-modal-subject_name">Mata Pelajaran <span class="req">*</span></label>
            <input id="subject-modal-subject_name" name="subject_name" type="text" maxlength="100" required list="subject-names" class="input" placeholder="Contoh: Matematika">
            <datalist id="subject-names">
                @foreach ($subjectNames as $subjectName)
                    <option value="{{ $subjectName }}"></option>
                @endforeach
            </datalist>
        </div>

        <div class="field">
            <label class="field-label" for="subject-modal-user_id">Guru Pengampu <span class="req">*</span></label>
            <select id="subject-modal-user_id" name="user_id" required class="select" data-searchable>
                <option value="">Pilih guru</option>
                @foreach ($activeTeachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field span-2" data-modal-show="create">
            <span class="field-label">Kelas <span class="req">*</span></span>
            <div class="field-help" style="margin-bottom:8px">
                Pilih satu atau beberapa kelas; satu penugasan dibuat per kelas.
                Kelas yang belum punya wali kelas di tahun ajaran ini tidak bisa dipilih — <a href="{{ route('admin.wali-kelas.index') }}">tetapkan wali kelas</a> dulu.
            </div>
            <div class="class-checks" data-modal-slot="class_checks"></div>
        </div>

        <div class="field" data-modal-show="edit">
            <label class="field-label" for="subject-modal-class_id">Kelas <span class="req">*</span></label>
            <select id="subject-modal-class_id" name="class_id" required class="select" data-modal-slot="class_options" data-searchable></select>
        </div>

        <div class="field">
            <label class="field-label" for="subject-modal-year">Tahun ajaran</label>
            <input id="subject-modal-year" class="input" type="text" value="{{ $activeYear->year_name }} (aktif)" readonly tabindex="-1">
            <div class="field-help">Otomatis terikat ke tahun ajaran aktif.</div>
        </div>
    </x-modal-form>
@endif
@endsection
