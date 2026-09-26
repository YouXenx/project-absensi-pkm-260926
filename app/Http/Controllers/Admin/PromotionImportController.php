<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionImportRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\PromotionImport;
use App\PromotionResult;
use App\StudentStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 2 of the yearly flow, the bulk way: download the template, upload it filled in, check the preview,
 * then confirm. Nothing is written before the confirmation, and every row is re-checked at that point.
 */
class PromotionImportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    /**
     * The .xlsx template, including the valid class names for the active year.
     */
    public function template(Request $request): StreamedResponse
    {
        /** @var AcademicYear $academicYear */
        $academicYear = $request->attributes->get('activeAcademicYear');
        $spreadsheet = PromotionImport::template($academicYear);
        $filename = 'Template Kenaikan Kelas '.str_replace('/', '-', $academicYear->year_name).'.xlsx';

        return response()->streamDownload(
            fn () => PromotionImport::write($spreadsheet),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Read the upload and show what would happen; nothing is saved here.
     */
    public function preview(PromotionImportRequest $request): View|RedirectResponse
    {
        /** @var AcademicYear $academicYear */
        $academicYear = $request->attributes->get('activeAcademicYear');

        try {
            $entries = PromotionImport::read($request->file('file')->getRealPath(), $academicYear);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'File tidak bisa dibaca. Pastikan formatnya .xlsx sesuai template.');
        }

        if ($entries->isEmpty()) {
            return back()->with('error', 'File tidak berisi baris data. Gunakan template dan isi minimal satu siswa.');
        }

        $valid = $entries->whereNull('error');

        return view('pages.admin.promotions.import-preview', [
            'academicYear' => $academicYear,
            'entries' => $entries,
            'validCount' => $valid->count(),
            'invalidCount' => $entries->count() - $valid->count(),
            'summary' => $valid
                ->groupBy(fn (array $entry): string => $entry['result'] === PromotionResult::Promoted
                    ? 'Naik ke '.$entry['toClass']->class_name
                    : $entry['result']->label())
                ->map->count(),
            'decisions' => $valid->mapWithKeys(fn (array $entry): array => [$entry['student']->student_id => PromotionImport::decisionFor($entry)]),
        ]);
    }

    /**
     * Apply the previewed plan. The decisions are resolved against the database again, so a stale preview
     * (someone else processed a student meanwhile) is rejected instead of writing twice.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var AcademicYear $academicYear */
        $academicYear = $request->attributes->get('activeAcademicYear');

        $validated = $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required', 'string', 'max:20'],
        ], [
            'decisions.required' => 'Tidak ada baris valid untuk diproses.',
        ]);

        $decisions = collect($validated['decisions'])->mapWithKeys(fn (string $value, int|string $key): array => [(int) $key => $value]);
        $students = Student::query()
            ->active()
            ->whereKey($decisions->keys())
            ->whereDoesntHave('promotions', fn ($query) => $query->where('academic_year_id', $academicYear->academic_year_id))
            ->get()
            ->keyBy('student_id');

        if ($students->count() !== $decisions->count()) {
            return back()->with('error', 'Sebagian siswa sudah diproses atau tidak lagi aktif. Unggah ulang file untuk memuat data terbaru.');
        }

        $classes = SchoolClass::whereKey($decisions->values()->filter(fn (string $value): bool => ctype_digit($value)))->get()->keyBy('class_id');
        $plan = $this->plan($decisions, $students, $classes);

        if ($plan === null) {
            return back()->with('error', 'Ada tujuan kenaikan kelas yang tidak valid. Unggah ulang file dan periksa kembali preview.');
        }

        try {
            DB::transaction(function () use ($plan, $academicYear, $request): void {
                foreach ($plan as $row) {
                    /** @var Student $student */
                    $student = $row['student'];

                    StudentPromotion::create([
                        'academic_year_id' => $academicYear->academic_year_id,
                        'student_id' => $student->student_id,
                        'from_class_id' => $student->class_id,
                        'to_class_id' => $row['toClass']?->class_id,
                        'result' => $row['result'],
                        'processed_by' => $request->user()->id,
                    ]);

                    match ($row['result']) {
                        PromotionResult::Promoted => $student->update(['class_id' => $row['toClass']->class_id]),
                        PromotionResult::Graduated => $student->update(['status' => StudentStatus::Graduated]),
                        PromotionResult::Retained => null,
                    };
                }
            });
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'Sebagian siswa sudah diproses oleh permintaan lain. Tidak ada perubahan yang disimpan.');
        }

        $summary = collect($plan)
            ->countBy(fn (array $row): string => $row['result']->label())
            ->map(fn (int $count, string $label): string => "{$count} {$label}")
            ->implode(', ');

        return redirect()->route('admin.kenaikan.index')->with('alert', [
            'icon' => 'success',
            'title' => 'Impor kenaikan kelas selesai',
            'text' => count($plan)." siswa diproses dari file Excel untuk tahun ajaran {$academicYear->year_name}: {$summary}.",
            'toast' => false,
        ]);
    }

    /**
     * @param  Collection<int, string>  $decisions
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, SchoolClass>  $classes
     * @return list<array{student: Student, result: PromotionResult, toClass: SchoolClass|null}>|null
     */
    private function plan(Collection $decisions, Collection $students, Collection $classes): ?array
    {
        $plan = [];

        foreach ($decisions as $studentId => $value) {
            $student = $students[$studentId];

            $row = match (true) {
                $value === 'graduate' => ['student' => $student, 'result' => PromotionResult::Graduated, 'toClass' => null],
                $value === 'stay' => ['student' => $student, 'result' => PromotionResult::Retained, 'toClass' => $student->schoolClass],
                ctype_digit($value) && isset($classes[(int) $value]) && (int) $value !== $student->class_id => ['student' => $student, 'result' => PromotionResult::Promoted, 'toClass' => $classes[(int) $value]],
                default => null,
            };

            if ($row === null) {
                return null;
            }

            $plan[] = $row;
        }

        return $plan;
    }
}
