@extends('layouts.admin')

@section('title', 'Preview Kenaikan Kelas')
@section('breadcrumbs', 'Tahun Ajaran | Kenaikan Kelas | Preview')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Preview · Tahun ajaran {{ $academicYear->year_name }}</span>
        <h1 class="hero-title">Periksa sebelum diproses</h1>
        <p class="hero-sub">Belum ada data yang diubah. {{ $plan->count() }} siswa dari {{ $fromClass->class_name }} akan diproses.</p>
    </div>
</section>

<div class="grid">
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap"><span class="eyebrow">Ringkasan</span><h2 class="card-title">{{ $plan->count() }} siswa</h2></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px" data-testid="promotion-summary">
            @foreach ($summary as $label => $count)
                <span class="badge primary">{{ $label }}: {{ $count }}</span>
            @endforeach
        </div>
    </section>

    <section class="col-12 card">
        <div class="table-scroll">
            <table class="table" data-testid="promotion-preview">
                <thead><tr><th>NIS</th><th>Nama</th><th>Dari</th><th>Hasil</th><th>Ke</th></tr></thead>
                <tbody>
                    @foreach ($plan as $row)
                        <tr>
                            <td class="cell-date">{{ $row['student']->nis }}</td>
                            <td class="cell-name">{{ $row['student']->name }}</td>
                            <td>{{ $fromClass->class_name }}</td>
                            <td>
                                @if ($row['result'] === \App\PromotionResult::Promoted)
                                    <span class="badge success">Naik kelas</span>
                                @elseif ($row['result'] === \App\PromotionResult::Graduated)
                                    <span class="badge purple">Lulus</span>
                                @else
                                    <span class="badge warning">Tinggal kelas</span>
                                @endif
                            </td>
                            <td>{{ $row['toClass']?->class_name ?? '— (status lulus, tetap tercatat di '.$fromClass->class_name.')' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.kenaikan.store') }}" data-loading="Memproses kenaikan kelas… Jangan tutup halaman ini."
            data-confirm="Proses kenaikan kelas untuk {{ $plan->count() }} siswa dari {{ $fromClass->class_name }}? Perubahan kelas dan status siswa akan disimpan sekaligus.">
            @csrf
            <input type="hidden" name="from_class_id" value="{{ $fromClass->class_id }}">
            @foreach ($decisions as $studentId => $decision)
                <input type="hidden" name="decisions[{{ $studentId }}]" value="{{ $decision }}">
            @endforeach

            <div class="form-actions">
                <a class="btn btn--ghost" href="{{ route('admin.kenaikan.index', ['from_class_id' => $fromClass->class_id]) }}">Kembali &amp; ubah</a>
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit">Proses Kenaikan Kelas</button>
            </div>
        </form>
    </section>
</div>
@endsection
