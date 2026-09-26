{{-- Preview of an uploaded promotion workbook. Expects $academicYear, $entries, $validCount, $invalidCount, $summary, $decisions. --}}
@extends('layouts.admin')

@section('title', 'Preview Impor Kenaikan Kelas')
@section('breadcrumbs', 'Tahun Ajaran | Kenaikan Kelas | Impor Excel')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Impor Excel · Tahun ajaran {{ $academicYear->year_name }}</span>
        <h1 class="hero-title">Periksa hasil pembacaan file</h1>
        <p class="hero-sub">Belum ada data yang diubah. {{ $validCount }} baris siap diproses{{ $invalidCount > 0 ? ", {$invalidCount} baris dilewati karena bermasalah" : '' }}.</p>
    </div>
</section>

<div class="grid">
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Ringkasan</span><h2 class="card-title">{{ $validCount }} siswa akan diproses</h2></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px" data-testid="import-summary">
            @forelse ($summary as $label => $count)
                <span class="badge primary">{{ $label }}: {{ $count }}</span>
            @empty
                <span class="badge danger">Tidak ada baris yang bisa diproses</span>
            @endforelse
            @if ($invalidCount > 0)
                <span class="badge danger">Bermasalah: {{ $invalidCount }}</span>
            @endif
        </div>
    </section>

    <section class="col-12 card">
        <div class="table-scroll">
            <table class="table" data-testid="import-preview">
                <thead>
                    <tr><th>Baris</th><th>NIS</th><th>Nama di file</th><th>Nama di database</th><th>Hasil</th><th>Keterangan</th></tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr @class(['is-invalid-row' => $entry['error']])>
                            <td class="cell-date">{{ $entry['row'] }}</td>
                            <td class="cell-date">{{ $entry['nis'] }}</td>
                            <td>{{ $entry['name'] ?: '—' }}</td>
                            <td class="cell-name">{{ $entry['student']?->name ?? '—' }}</td>
                            <td>
                                @if ($entry['error'])
                                    <span class="badge danger">Dilewati</span>
                                @elseif ($entry['result'] === \App\PromotionResult::Promoted)
                                    <span class="badge success">Naik ke {{ $entry['toClass']->class_name }}</span>
                                @elseif ($entry['result'] === \App\PromotionResult::Graduated)
                                    <span class="badge purple">Lulus</span>
                                @else
                                    <span class="badge warning">Tinggal kelas</span>
                                @endif
                            </td>
                            <td>{{ $entry['error'] ?? 'Siap diproses' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.kenaikan.impor.store') }}"
            data-loading="Memproses kenaikan kelas… Jangan tutup halaman ini."
            data-confirm="Proses kenaikan kelas untuk {{ $validCount }} siswa dari file Excel? Perubahan kelas dan status siswa akan disimpan sekaligus.">
            @csrf
            @foreach ($decisions as $studentId => $decision)
                <input type="hidden" name="decisions[{{ $studentId }}]" value="{{ $decision }}">
            @endforeach

            <div class="form-actions">
                <a class="btn btn--ghost" href="{{ route('admin.kenaikan.index') }}">Kembali &amp; unggah ulang</a>
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit" @disabled($validCount === 0)>Proses {{ $validCount }} Siswa</button>
            </div>
        </form>
    </section>
</div>
@endsection
