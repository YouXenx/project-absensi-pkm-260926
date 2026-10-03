@extends('layouts.admin')

@section('title', 'Mapel yang Diampu')
@section('breadcrumbs', 'Guru | Mapel yang Diampu')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Operasi harian{{ $academicYear ? ' · Tahun ajaran '.$academicYear->year_name : '' }}</span>
        <h1 class="hero-title">Mapel yang Diampu</h1>
        <p class="hero-sub">Mata pelajaran yang ditugaskan kepada Anda di tahun ajaran aktif. Status absensi mengacu pada hari ini, {{ now()->format('d/m/Y') }}.</p>
    </div>
</section>

<section class="card">
    @if (! $academicYear)
        <p class="hero-sub" style="margin:0">Belum ada tahun ajaran aktif.</p>
    @else
        <x-data-table :url="route('guru.mapel.data')" search-placeholder="Cari mapel atau kelas…">
            <th data-data="subject_name">Mata Pelajaran</th>
            <th data-data="class_name">Kelas</th>
            <th data-data="active_students_count">Siswa Aktif</th>
            <th data-data="today">Absensi Hari Ini</th>
            <th data-data="actions" data-orderable="false" data-class="text-end" class="text-end">Aksi</th>
        </x-data-table>
    @endif
</section>
@endsection
