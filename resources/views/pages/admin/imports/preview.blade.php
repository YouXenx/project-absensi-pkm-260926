{{-- Preview of an uploaded master data workbook. Expects $type, $import, $entries, $validCount, $invalidCount. --}}
@extends('layouts.admin')

@section('title', 'Preview Impor '.$import->title())
@section('breadcrumbs', $import->title().' | Impor Excel')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Impor Excel · {{ $import->title() }}</span>
        <h1 class="hero-title">Periksa hasil pembacaan file</h1>
        <p class="hero-sub">Belum ada data yang disimpan. {{ $validCount }} baris siap diimpor{{ $invalidCount > 0 ? ", {$invalidCount} baris dilewati karena bermasalah" : '' }}.</p>
    </div>
</section>

<div class="grid">
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Ringkasan</span><h2 class="card-title">{{ $validCount }} {{ $import->noun() }} akan ditambahkan</h2></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px" data-testid="import-summary">
            @if ($validCount > 0)
                <span class="badge success">Siap diimpor: {{ $validCount }}</span>
            @else
                <span class="badge danger">Tidak ada baris yang bisa diimpor</span>
            @endif
            @if ($invalidCount > 0)
                <span class="badge danger">Bermasalah: {{ $invalidCount }}</span>
            @endif
        </div>
    </section>

    <section class="col-12 card">
        <div class="table-scroll">
            <table class="table" data-testid="import-preview">
                <thead>
                    <tr>
                        <th>Baris</th>
                        @foreach ($import->headers() as $heading)<th>{{ $heading }}</th>@endforeach
                        <th>Hasil</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr @class(['is-invalid-row' => $entry['error']])>
                            <td class="cell-date">{{ $entry['row'] }}</td>
                            @foreach ($entry['cells'] as $column => $cell)
                                {{-- Passwords are never shown back; only whether one was given. --}}
                                <td>{{ in_array($column, $import->hiddenColumns(), true) ? ($cell === '' ? '—' : '••••••••') : ($cell === '' ? '—' : $cell) }}</td>
                            @endforeach
                            <td><span @class(['badge', 'danger' => $entry['error'], 'success' => ! $entry['error']])>{{ $entry['error'] ? 'Dilewati' : 'Siap' }}</span></td>
                            <td>{{ $entry['error'] ?? 'Siap diimpor' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.impor.store', $type) }}"
            data-loading="Menyimpan data… Jangan tutup halaman ini."
            data-confirm="Impor {{ $validCount }} {{ $import->noun() }} dari file Excel? Data akan disimpan sekaligus.">
            @csrf
            <input type="hidden" name="expected" value="{{ $validCount }}">

            <div class="form-actions">
                <a class="btn btn--ghost" href="{{ route($import->indexRoute()) }}">Kembali &amp; unggah ulang</a>
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit" @disabled($validCount === 0)>Impor {{ $validCount }} Baris</button>
            </div>
        </form>
    </section>
</div>
@endsection
