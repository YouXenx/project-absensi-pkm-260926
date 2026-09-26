@extends('layouts.admin')

@section('title', 'Wali Kelas')
@section('breadcrumbs', 'Tahun Ajaran | Wali Kelas')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Langkah 3</span>
        <h1 class="hero-title">Wali Kelas</h1>
        <p class="hero-sub">Satu kelas hanya punya satu wali kelas per tahun ajaran. Tahun ajaran lama hanya bisa dilihat.</p>
    </div>
    @if ($activeYear)
        <div class="hero-actions">
            <button type="button" class="btn btn--primary" data-modal-open="homeroom-modal" data-modal-create data-modal-url="{{ route('admin.wali-kelas.create') }}" data-modal-hash="tambah">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Tetapkan Wali Kelas
            </button>
        </div>
    @endif
</section>

<section class="card">
    @if (! $selectedYear)
        <p class="hero-sub" style="margin:0">Belum ada tahun ajaran. <a href="{{ route('admin.tahun-ajaran.index') }}">Buat tahun ajaran</a> terlebih dahulu.</p>
    @else
        <x-data-table :url="route('admin.wali-kelas.data')" search-placeholder="Cari kelas atau guru…" export-title="Wali Kelas">
            <x-slot:filters>
                <select class="select" data-datatable-filter="academic_year_id" aria-label="Filter tahun ajaran">
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->academic_year_id }}" @selected($selectedYear->is($year))>{{ $year->year_name }}{{ $year->is_active ? ' (aktif)' : ' (histori)' }}</option>
                    @endforeach
                </select>
            </x-slot:filters>

            <th data-data="class_name">Kelas</th>
            <th data-data="active_students_count">Siswa Aktif</th>
            <th data-data="teacher_name">Wali Kelas</th>
            <th data-data="actions" data-orderable="false" data-class="text-end" style="width:110px"></th>
        </x-data-table>
    @endif
</section>

@if ($activeYear)
    <x-modal-form id="homeroom-modal" title="Tetapkan Wali Kelas" edit-title="Ganti Wali Kelas" :action="route('admin.wali-kelas.store')">
        <div class="field">
            <label class="field-label" for="homeroom-modal-year">Tahun ajaran</label>
            <input id="homeroom-modal-year" class="input" type="text" value="{{ $activeYear->year_name }} (aktif)" readonly tabindex="-1">
            <div class="field-help">Otomatis terikat ke tahun ajaran aktif.</div>
        </div>

        <div class="field">
            <label class="field-label" for="homeroom-modal-class_id">Kelas <span class="req">*</span></label>
            <select id="homeroom-modal-class_id" name="class_id" required class="select" data-modal-slot="class_options" data-searchable></select>
        </div>

        <div class="field span-2">
            <label class="field-label" for="homeroom-modal-user_id">Guru <span class="req">*</span></label>
            <select id="homeroom-modal-user_id" name="user_id" required class="select" data-searchable>
                <option value="">Pilih guru</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>
    </x-modal-form>
@endif
@endsection
