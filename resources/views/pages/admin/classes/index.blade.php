@extends('layouts.admin')

@section('title', 'Data Kelas')
@section('breadcrumbs', 'Master Data | Data Kelas')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Master Data</span>
        <h1 class="hero-title">Data Kelas</h1>
        <p class="hero-sub">Kelola daftar kelas. Kelas yang masih memiliki siswa tidak bisa dihapus.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary" data-modal-open="class-modal" data-modal-hash="tambah">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Tambah Kelas
        </button>
    </div>
</section>

<section class="card">
    <x-data-table :url="route('admin.kelas.data')" search-placeholder="Cari nama kelas…">
        <th data-data="class_name">Nama Kelas</th>
        <th data-data="students_count">Jumlah Siswa</th>
        <th data-data="actions" data-orderable="false" data-class="text-end" style="width:90px"></th>
    </x-data-table>
</section>

<x-modal-form id="class-modal" title="Tambah Kelas" edit-title="Edit Kelas" :action="route('admin.kelas.store')">
    <div class="field span-2">
        <label class="field-label" for="class-modal-class_name">Nama Kelas <span class="req">*</span></label>
        <input id="class-modal-class_name" name="class_name" type="text" maxlength="50" required class="input" placeholder="Contoh: Kelas 1A">
    </div>
</x-modal-form>
@endsection
