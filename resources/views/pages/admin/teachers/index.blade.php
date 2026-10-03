@extends('layouts.admin')

@section('title', 'Data Guru')
@section('breadcrumbs', 'Master Data | Data Guru')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Kelola Akun Guru</span>
        <h1 class="hero-title">Data Guru</h1>
        <p class="hero-sub">Tambah, edit, atau nonaktifkan akun guru. Guru yang dinonaktifkan tidak bisa login dan langsung keluar dari sesi aktifnya.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary" data-modal-open="teacher-modal" data-modal-hash="tambah">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Tambah Guru
        </button>
    </div>
</section>

<section class="card">
    <x-data-table :url="route('admin.guru.data')" search-placeholder="Cari nama atau email…" export-title="Data Guru">
        <x-slot:filters>
            <select class="select" data-datatable-filter="status" aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </x-slot:filters>

        <th data-data="name">Nama</th>
        <th data-data="email">Email</th>
        <th data-data="status">Status</th>
        <th data-data="created_at">Terdaftar</th>
        <th data-data="actions" data-orderable="false" data-class="text-end" class="text-end">Aksi</th>
    </x-data-table>
</section>

<x-modal-form id="teacher-modal" title="Tambah Akun Guru" edit-title="Edit Akun Guru" :action="route('admin.guru.store')">
    <div class="field">
        <label class="field-label" for="teacher-modal-name">Nama Lengkap <span class="req">*</span></label>
        <input id="teacher-modal-name" name="name" type="text" maxlength="255" required class="input">
    </div>

    <div class="field">
        <label class="field-label" for="teacher-modal-email">Email <span class="req">*</span></label>
        <input id="teacher-modal-email" name="email" type="email" maxlength="255" required autocomplete="off" class="input" placeholder="nama@sekolah.sch.id">
    </div>

    <div class="field">
        <label class="field-label" for="teacher-modal-password">Password <span class="req" data-modal-show="create">*</span></label>
        <x-password-input id="teacher-modal-password" name="password" autocomplete="new-password" />
        <div class="field-help" data-modal-show="create">Minimal 8 karakter. Bebas memakai huruf besar, huruf kecil, angka, dan simbol.</div>
        <div class="field-help" data-modal-show="edit">Kosongkan jika tidak ingin mengganti password.</div>
    </div>

    <div class="field">
        <label class="field-label" for="teacher-modal-password_confirmation">Konfirmasi Password</label>
        <x-password-input id="teacher-modal-password_confirmation" name="password_confirmation" autocomplete="new-password" />
    </div>

    <div class="field span-2">
        <input type="hidden" name="is_active" value="0">
        <label class="check">
            <input type="checkbox" name="is_active" value="1" checked>
            <span class="box"></span> Akun aktif (guru bisa login)
        </label>
        <div class="field-help">Akun baru otomatis memiliki role guru.</div>
    </div>
</x-modal-form>
@endsection
