<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportFileRequest;
use App\Imports\MasterImport;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel import for the master data modules: download the template, upload it filled in, check the preview,
 * then confirm. Nothing is written before the confirmation, and every row is checked again at that point.
 */
class MasterImportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    /**
     * The .xlsx template of one module, with instructions and the valid values on a second sheet.
     */
    public function template(string $type): StreamedResponse|RedirectResponse
    {
        $import = $this->importer($type);

        if ($import instanceof RedirectResponse) {
            return $import;
        }

        $spreadsheet = $import->template();

        return response()->streamDownload(
            fn () => MasterImport::write($spreadsheet),
            "Template {$import->title()}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Read the upload and show what would be created; nothing is saved here.
     */
    public function preview(ImportFileRequest $request, string $type): View|RedirectResponse
    {
        $import = $this->importer($type);

        if ($import instanceof RedirectResponse) {
            return $import;
        }

        try {
            $rows = $import->read($request->file('file')->getRealPath());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'File tidak bisa dibaca. Pastikan formatnya .xlsx sesuai template.');
        }

        if ($rows->isEmpty()) {
            return back()->with('error', 'File tidak berisi baris data. Gunakan template dan isi minimal satu baris.');
        }

        $entries = $import->validate($rows);

        // The rows wait in the session until the admin confirms. They can hold first passwords (guru), so they
        // are dropped as soon as the import is stored or replaced by another upload.
        $request->session()->put($this->sessionKey($type), $rows->all());

        return view('pages.admin.imports.preview', [
            'type' => $type,
            'import' => $import,
            'entries' => $entries,
            'validCount' => $entries->whereNull('error')->count(),
            'invalidCount' => $entries->whereNotNull('error')->count(),
        ]);
    }

    /**
     * Create the previewed records. The rows are checked against the database again, so a preview that went
     * stale (someone added the same NIS meanwhile) is rejected instead of half applied.
     */
    public function store(Request $request, string $type): RedirectResponse
    {
        $import = $this->importer($type);

        if ($import instanceof RedirectResponse) {
            return $import;
        }

        $rows = $request->session()->pull($this->sessionKey($type));

        if (! is_array($rows) || $rows === []) {
            return redirect()->route($import->indexRoute())->with('error', 'Preview impor sudah tidak berlaku. Unggah ulang file Excel.');
        }

        $entries = $import->validate(collect($rows));
        $validCount = $entries->whereNull('error')->count();

        if ($validCount === 0 || $validCount !== $request->integer('expected')) {
            return redirect()->route($import->indexRoute())->with('error', 'Data berubah sejak preview dibuat. Unggah ulang file Excel untuk memuat hasil terbaru.');
        }

        $created = $import->store($entries);
        $skipped = $entries->count() - $created;

        return redirect()->route($import->indexRoute())->with(
            'success',
            "{$created} {$import->noun()} berhasil diimpor dari Excel".($skipped > 0 ? ", {$skipped} baris dilewati." : '.'),
        );
    }

    /**
     * The importer for a URL type, or a redirect when it needs an active academic year and there is none.
     */
    private function importer(string $type): MasterImport|RedirectResponse
    {
        $import = MasterImport::for($type);

        if (! $import->needsActiveYear()) {
            return $import;
        }

        $academicYear = AcademicYear::current();

        return $academicYear
            ? $import->forYear($academicYear)
            : redirect()->route($import->indexRoute())->with('error', 'Buat dan aktifkan tahun ajaran terlebih dahulu sebelum mengimpor.');
    }

    private function sessionKey(string $type): string
    {
        return "master-import.{$type}";
    }
}
