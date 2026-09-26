{{-- Expects $teacher (always the signed-in guru), $academicYear, $subjects and $homeroomClasses (both scoped to $teacher). --}}
@extends('layouts.admin')

@section('title', 'Dashboard Guru')
@section('breadcrumbs', 'Guru | Dashboard')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Dashboard Guru{{ $academicYear ? ' · Tahun ajaran '.$academicYear->year_name : '' }}</span>
        <h1 class="hero-title">Selamat datang, <span class="accent">{{ $teacher->name }}</span></h1>
        <p class="hero-sub">Halaman ini hanya menampilkan data milik Anda sendiri.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="{{ route('guru.rekap.index') }}">Rekap Absensi</a>
        <a class="btn btn--primary" href="{{ route('guru.profil.edit') }}">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya
        </a>
    </div>
</section>

<div class="grid">
    <section class="col-6 card" data-section="my-subjects">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Mengajar</span><h2 class="card-title">Mata pelajaran yang saya ampu</h2></div>
            <span class="card-action">{{ $subjects->count() }} mapel</span>
        </div>
        @if (! $academicYear)
            <p class="hero-sub" style="margin:0">Belum ada tahun ajaran aktif.</p>
        @else
            <table class="table">
                <thead><tr><th>Mapel</th><th>Kelas</th><th>Siswa aktif</th></tr></thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td class="cell-name">{{ $subject->subject_name }}</td>
                            <td>{{ $subject->schoolClass->class_name }}</td>
                            <td>{{ $subject->schoolClass->students_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="cell-date">Belum ada mapel yang ditugaskan kepada Anda di tahun ajaran ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </section>

    <section class="col-6 card" data-section="my-account">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Akun</span><h2 class="card-title">Informasi akun saya</h2></div>
            <a class="card-action" href="{{ route('guru.profil.edit') }}">Ubah <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
        </div>
        <table class="table">
            <tbody>
                <tr><td class="cell-date">Nama</td><td class="cell-name">{{ $teacher->name }}</td></tr>
                <tr><td class="cell-date">Email</td><td>{{ $teacher->email }}</td></tr>
                <tr><td class="cell-date">Role</td><td>{{ $teacher->role->label() }}</td></tr>
                <tr><td class="cell-date">Status</td><td>@include('partials.status-badge', ['isActive' => $teacher->is_active])</td></tr>
                <tr>
                    <td class="cell-date">Wali kelas</td>
                    <td data-testid="my-homeroom">{{ $homeroomClasses->isEmpty() ? '—' : $homeroomClasses->map(fn ($class) => "{$class->class_name} ({$class->students_count} siswa)")->implode(', ') }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</div>
@endsection
