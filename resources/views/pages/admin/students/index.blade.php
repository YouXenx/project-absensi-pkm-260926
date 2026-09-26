@extends('layouts.admin')

@section('title', 'Data Siswa')
@section('breadcrumbs', 'Master Data | Data Siswa')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Master Data</span>
        <h1 class="hero-title">Data Siswa</h1>
        <p class="hero-sub">Cari berdasarkan NIS, nama, atau kelas. Data dimuat per halaman dari server.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary" data-modal-open="student-modal" data-modal-hash="tambah">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Tambah Siswa
        </button>
    </div>
</section>

<section class="card">
    <x-data-table :url="route('admin.siswa.data')" search-placeholder="Cari NIS, nama, atau kelas…" export-title="Data Siswa">
        <x-slot:filters>
            <select class="select" data-datatable-filter="class_id" aria-label="Filter kelas" data-searchable data-searchable-width="180px">
                <option value="">Semua kelas</option>
                @foreach ($classes as $schoolClass)
                    <option value="{{ $schoolClass->class_id }}">{{ $schoolClass->class_name }}</option>
                @endforeach
            </select>
            <select class="select" data-datatable-filter="status" aria-label="Filter status">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($status->isEnrolled())>{{ $status->label() }}</option>
                @endforeach
                <option value="">Semua status</option>
            </select>
        </x-slot:filters>

        <th data-data="nis">NIS</th>
        <th data-data="name">Nama</th>
        <th data-data="class_name">Kelas</th>
        <th data-data="gender">Jenis Kelamin</th>
        <th data-data="status">Status</th>
        <th data-data="actions" data-orderable="false" data-class="text-end" style="width:90px"></th>
    </x-data-table>
</section>

<x-modal-form id="student-modal" title="Tambah Siswa" edit-title="Edit Siswa" :action="route('admin.siswa.store')">
    <div class="field">
        <label class="field-label" for="student-modal-nis">NIS <span class="req">*</span></label>
        <input id="student-modal-nis" name="nis" type="text" inputmode="numeric" maxlength="20" required class="input" placeholder="Contoh: 2026000123">
    </div>

    <div class="field" data-modal-show="create">
        <label class="field-label" for="student-modal-class_id">Kelas <span class="req">*</span></label>
        <select id="student-modal-class_id" name="class_id" required class="select" data-searchable>
            <option value="">{{ $classes->isEmpty() ? 'Belum ada kelas' : 'Pilih kelas' }}</option>
            @foreach ($classes as $schoolClass)
                <option value="{{ $schoolClass->class_id }}">{{ $schoolClass->class_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="field" data-modal-show="edit">
        <label class="field-label" for="student-modal-current_class">Kelas</label>
        <input id="student-modal-current_class" class="input" type="text" readonly tabindex="-1" data-modal-text="current_class">
        <div class="field-help" data-modal-text="class_note"></div>
    </div>

    <div class="field span-2">
        <label class="field-label" for="student-modal-name">Nama Lengkap <span class="req">*</span></label>
        <input id="student-modal-name" name="name" type="text" maxlength="255" required class="input">
    </div>

    <div class="field span-2">
        <span class="field-label">Jenis Kelamin <span class="req">*</span></span>
        <div style="display:flex;gap:24px;flex-wrap:wrap">
            @foreach ($genders as $gender)
                <label class="check radio">
                    <input type="radio" name="gender" value="{{ $gender->value }}">
                    <span class="box"></span> {{ $gender->label() }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="field" data-modal-show="edit">
        <label class="field-label" for="student-modal-status">Status</label>
        <select id="student-modal-status" name="status" class="select">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
        <div class="field-help">Biasanya diubah otomatis lewat proses kenaikan kelas.</div>
    </div>
</x-modal-form>

<x-modal-form id="status-modal" title="Ubah Status Siswa" :action="route('admin.siswa.index')" method="PATCH" submit-label="Simpan Status">
    <div class="field span-2">
        <p class="modal-note" style="margin:0">
            Siswa yang berstatus selain <strong>Aktif</strong> tidak muncul lagi di daftar absensi, roster kelas, dan proses kenaikan kelas.
            Datanya tetap tersimpan sebagai histori, termasuk absensi yang sudah tercatat.
        </p>
    </div>
    <div class="field">
        <label class="field-label" for="status-modal-student">Siswa</label>
        <input id="status-modal-student" class="input" type="text" readonly tabindex="-1" data-modal-text="student_name">
    </div>
    <div class="field">
        <label class="field-label" for="status-modal-current">Status saat ini</label>
        <input id="status-modal-current" class="input" type="text" readonly tabindex="-1" data-modal-text="current_status">
    </div>
    <div class="field span-2">
        <span class="field-label">Status baru <span class="req">*</span></span>
        <div class="status-radios">
            @foreach ($statuses as $status)
                <label class="check radio">
                    <input type="radio" name="status" value="{{ $status->value }}">
                    <span class="box"></span> {{ $status->label() }}
                </label>
            @endforeach
        </div>
    </div>
    <div class="field span-2">
        <label class="field-label" for="status-modal-note">Keterangan</label>
        <input id="status-modal-note" name="note" type="text" maxlength="255" class="input" placeholder="Opsional, contoh: pindah ke SDN 2 Sleman">
    </div>
</x-modal-form>

<x-modal-form id="transfer-modal" title="Pindah Kelas" :action="route('admin.siswa.index')" method="PATCH" submit-label="Pindahkan">
    <div class="field span-2">
        <p class="modal-note" style="margin:0">Untuk perpindahan satu siswa di luar proses kenaikan kelas. Absensi yang sudah tercatat tetap menjadi histori kelas lama.</p>
    </div>
    <div class="field">
        <label class="field-label" for="transfer-modal-nis">NIS</label>
        <input id="transfer-modal-nis" class="input" type="text" readonly tabindex="-1" data-modal-text="nis">
    </div>
    <div class="field">
        <label class="field-label" for="transfer-modal-current_class">Kelas saat ini</label>
        <input id="transfer-modal-current_class" class="input" type="text" readonly tabindex="-1" data-modal-text="current_class">
    </div>
    <div class="field span-2">
        <label class="field-label" for="transfer-modal-class_id">Kelas tujuan <span class="req">*</span></label>
        <select id="transfer-modal-class_id" name="class_id" required class="select" data-modal-slot="target_classes" data-searchable></select>
    </div>
</x-modal-form>
@endsection
