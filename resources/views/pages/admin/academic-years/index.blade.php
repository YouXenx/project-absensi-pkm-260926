@extends('layouts.admin')

@section('title', 'Tahun Ajaran')
@section('breadcrumbs', 'Tahun Ajaran | Daftar')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Operasi Tahunan</span>
        <h1 class="hero-title">Tahun Ajaran</h1>
        <p class="hero-sub">Urutan: buat tahun ajaran → kenaikan kelas → wali kelas → mata pelajaran → siswa siap diabsen. Tahun ajaran lama tersimpan sebagai histori.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary" data-modal-open="year-modal" data-modal-create data-modal-url="{{ route('admin.tahun-ajaran.create') }}" data-modal-hash="tambah">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Tahun Ajaran Baru
        </button>
    </div>
</section>

<div class="grid">
    <section class="col-6 card" data-fragment="current-year">
        @include('pages.admin.academic-years.current')
    </section>

    <section class="col-6 card">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Histori</span><h2 class="card-title">Semua tahun ajaran</h2></div>
        </div>
        <x-data-table :url="route('admin.tahun-ajaran.data')" search-placeholder="Cari tahun ajaran…">
            <th data-data="year_name">Tahun</th>
            <th data-data="status" data-orderable="false">Status</th>
            <th data-data="homeroom_teachers_count">Wali</th>
            <th data-data="subjects_count">Mapel</th>
            <th data-data="promotions_count">Kenaikan</th>
            <th data-data="attendances_count">Absensi</th>
            <th data-data="actions" data-orderable="false" data-class="text-end" class="text-end">Aksi</th>
        </x-data-table>
    </section>
</div>

<x-modal-form id="year-modal" title="Buat Tahun Ajaran Baru" :action="route('admin.tahun-ajaran.store')" submit-label="Buat & Aktifkan">
    <div class="field span-2">
        <p class="modal-note" style="margin:0" data-modal-text="current_note"></p>
    </div>
    <div class="field span-2">
        <label class="field-label" for="year-modal-year_name">Nama Tahun Ajaran <span class="req">*</span></label>
        <input id="year-modal-year_name" name="year_name" type="text" maxlength="9" required class="input" placeholder="2026/2027">
        <div class="field-help">Format YYYY/YYYY, contoh 2026/2027.</div>
    </div>
    <div class="field span-2" data-modal-slot="incomplete"></div>
</x-modal-form>
@endsection
