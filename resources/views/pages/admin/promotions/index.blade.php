@extends('layouts.admin')

@section('title', 'Kenaikan Kelas')
@section('breadcrumbs', 'Tahun Ajaran | Kenaikan Kelas')

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Langkah 2 · Tahun ajaran {{ $academicYear->year_name }}</span>
        <h1 class="hero-title">Kenaikan Kelas</h1>
        <p class="hero-sub">Pilih kelas asal, tentukan tujuan tiap siswa (naik, tinggal kelas, atau lulus), lihat preview, lalu konfirmasi. Setiap siswa hanya diproses sekali per tahun ajaran. {{ $totalProcessed }} siswa sudah diproses.</p>
    </div>
</section>

<div class="grid">
    <section class="col-12 card">
        <form method="GET" action="{{ route('admin.kenaikan.index') }}" class="form-grid" data-loading="Memuat siswa…">
            <div class="field">
                <label class="field-label" for="from_class_id">Kelas asal</label>
                <select id="from_class_id" name="from_class_id" class="select" data-auto-submit data-searchable>
                    <option value="">Pilih kelas asal</option>
                    @foreach ($classes as $schoolClass)
                        <option value="{{ $schoolClass->class_id }}" @selected($fromClass?->class_id === $schoolClass->class_id)>
                            {{ $schoolClass->class_name }} — {{ $schoolClass->pending_students_count }} belum diproses, {{ $schoolClass->processed_students_count }} sudah
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="align-self:end">
                <noscript><button class="btn btn--ghost" type="submit">Tampilkan</button></noscript>
            </div>
        </form>

        <div class="table-scroll" style="margin-top:6px">
            <table class="table" data-testid="promotion-progress">
                <thead>
                    <tr><th>Kelas</th><th class="num">Siswa aktif</th><th class="num">Sudah diproses</th><th class="num">Belum</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($classes as $schoolClass)
                        <tr @class(['is-selected-row' => $fromClass?->class_id === $schoolClass->class_id])>
                            <td class="cell-name">{{ $schoolClass->class_name }}</td>
                            <td class="num">{{ $schoolClass->active_students_count }}</td>
                            <td class="num">{{ $schoolClass->processed_students_count }}</td>
                            <td class="num">{{ $schoolClass->pending_students_count }}</td>
                            <td>@include('pages.admin.promotions.class-status', ['schoolClass' => $schoolClass])</td>
                            <td class="text-end">
                                @if ($schoolClass->pending_students_count > 0)
                                    <a class="btn btn--ghost" style="padding:5px 10px;font-size:12px" href="{{ route('admin.kenaikan.index', ['from_class_id' => $schoolClass->class_id]) }}">Proses</a>
                                @elseif ($schoolClass->processed_students_count > 0)
                                    <a class="cell-date" href="{{ route('admin.kenaikan.index', ['from_class_id' => $schoolClass->class_id]) }}">Lihat histori</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="col-12 card" data-section="promotion-import">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Alternatif</span>
                <h2 class="card-title">Impor kenaikan kelas dari Excel</h2>
            </div>
            <a class="btn btn--ghost" href="{{ route('admin.kenaikan.template') }}">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>
                Download Template Excel
            </a>
        </div>
        <p class="hero-sub" style="margin:0 0 14px">
            Untuk memproses banyak kelas sekaligus. Kolom template: <strong>NIS</strong>, <strong>Nama Siswa</strong>, <strong>Kelas Asal</strong>,
            dan <strong>Kelas Tujuan / Status</strong> (nama kelas, <code>{{ \App\PromotionImport::RETAIN }}</code>, atau <code>{{ \App\PromotionImport::GRADUATE }}</code>).
            Isi file akan diperiksa dan ditampilkan sebagai preview dulu; data baru tersimpan setelah Anda konfirmasi.
        </p>
        <form method="POST" action="{{ route('admin.kenaikan.impor') }}" enctype="multipart/form-data" class="form-grid" data-loading="Memeriksa isi file Excel…">
            @csrf
            <div class="field">
                <label class="field-label" for="import-file">File Excel <span class="req">*</span></label>
                <input id="import-file" name="file" type="file" accept=".xlsx,.xls,.csv" required
                    @class(['input', 'is-invalid' => $errors->has('file')])>
                <div class="field-help">Format .xlsx, .xls, atau .csv. Maksimal 2 MB dan {{ number_format(\App\PromotionImport::MAX_ROWS, 0, ',', '.') }} baris.</div>
                @error('file')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="field" style="align-self:end">
                <button class="btn btn--primary" type="submit">Periksa &amp; Preview</button>
            </div>
        </form>
    </section>

    @if ($fromClass)
        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Belum diproses</span>
                    <h2 class="card-title">Siswa {{ $fromClass->class_name }}</h2>
                </div>
                <div class="card-head-actions">
                    @include('pages.admin.promotions.class-status', ['schoolClass' => $fromClass])
                    <span class="card-action">{{ $fromClass->processed_students_count }} sudah diproses · {{ $fromClass->pending_students_count }} belum</span>
                </div>
            </div>

            @if ($pendingStudents->isEmpty())
                <p class="hero-sub" style="margin:0">Semua siswa aktif di {{ $fromClass->class_name }} sudah diproses untuk tahun ajaran {{ $academicYear->year_name }}.</p>
            @else
                <form method="POST" action="{{ route('admin.kenaikan.preview') }}" data-loading="Menyiapkan preview kenaikan kelas…">
                    @csrf
                    <input type="hidden" name="from_class_id" value="{{ $fromClass->class_id }}">

                    <div class="data-toolbar">
                        <div class="data-toolbar-left">
                            <label class="field-label" for="bulk-decision">Terapkan ke semua siswa (per rombongan kelas)</label>
                            <select id="bulk-decision" class="select" data-apply-all="decision" style="max-width:360px">
                                <option value="">— pilih aksi rombongan —</option>
                                @include('pages.admin.promotions.options', ['fromClass' => $fromClass, 'classes' => $classes, 'selected' => null])
                            </select>
                        </div>
                    </div>

                    <div class="table-scroll">
                        <table class="table">
                            <thead><tr><th>NIS</th><th>Nama</th><th>Kelas asal</th><th style="width:320px">Tujuan</th></tr></thead>
                            <tbody>
                                @foreach ($pendingStudents as $student)
                                    <tr>
                                        <td class="cell-date">{{ $student->nis }}</td>
                                        <td class="cell-name">{{ $student->name }}</td>
                                        <td>{{ $fromClass->class_name }}</td>
                                        <td>
                                            <select class="select" name="decisions[{{ $student->student_id }}]" data-apply-all-target="decision" aria-label="Tujuan {{ $student->name }}">
                                                @include('pages.admin.promotions.options', [
                                                    'fromClass' => $fromClass,
                                                    'classes' => $classes,
                                                    'selected' => old("decisions.{$student->student_id}", \App\Http\Requests\Admin\PromotionRequest::SKIP),
                                                ])
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @error('decisions')
                        <div class="field-error" style="margin-top:12px">{{ $message }}</div>
                    @enderror

                    <div class="form-actions">
                        <span class="spacer"></span>
                        <button class="btn btn--primary" type="submit">Preview Kenaikan Kelas</button>
                    </div>
                </form>
            @endif
        </section>

        @if ($processed->isNotEmpty())
            <section class="col-12 card">
                <div class="card-head">
                    <div class="card-title-wrap"><span class="eyebrow">Histori {{ $academicYear->year_name }}</span><h2 class="card-title">Sudah diproses dari {{ $fromClass->class_name }}</h2></div>
                </div>
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>NIS</th><th>Nama</th><th>Hasil</th><th>Kelas tujuan</th></tr></thead>
                        <tbody>
                            @foreach ($processed as $promotion)
                                <tr>
                                    <td class="cell-date">{{ $promotion->student->nis }}</td>
                                    <td class="cell-name">{{ $promotion->student->name }}</td>
                                    <td>
                                        @if ($promotion->result === \App\PromotionResult::Promoted)
                                            <span class="badge success">Naik kelas</span>
                                        @elseif ($promotion->result === \App\PromotionResult::Graduated)
                                            <span class="badge purple">Lulus</span>
                                        @else
                                            <span class="badge warning">Tinggal kelas</span>
                                        @endif
                                    </td>
                                    <td>{{ $promotion->toClass?->class_name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
