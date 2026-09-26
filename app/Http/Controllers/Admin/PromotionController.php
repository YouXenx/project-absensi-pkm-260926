<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\PromotionResult;
use App\StudentStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Step 2 of the yearly flow: choose -> preview -> confirm. Only runs for the active academic year,
 * and every student is processed at most once per year.
 */
class PromotionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    public function index(Request $request): View
    {
        /** @var AcademicYear $academicYear */
        $academicYear = $request->attributes->get('activeAcademicYear');

        $classes = SchoolClass::query()
            ->withCount([
                'students as active_students_count' => fn ($query) => $query->active(),
                'students as pending_students_count' => fn ($query) => $query->active()
                    ->whereDoesntHave('promotions', fn ($query) => $query->where('academic_year_id', $academicYear->academic_year_id)),
                // Already processed this year: drives the "sudah/belum diproses" indicator on the page.
                'promotionsFrom as processed_students_count' => fn ($query) => $query->where('academic_year_id', $academicYear->academic_year_id),
            ])
            ->orderBy('class_name')
            ->get();

        $fromClass = $classes->firstWhere('class_id', $request->integer('from_class_id'));

        $pendingStudents = collect();
        $processed = collect();

        if ($fromClass) {
            $pendingStudents = Student::query()
                ->active()
                ->where('class_id', $fromClass->class_id)
                ->whereDoesntHave('promotions', fn ($query) => $query->where('academic_year_id', $academicYear->academic_year_id))
                ->orderBy('name')
                ->get();

            $processed = StudentPromotion::query()
                ->with(['student', 'toClass'])
                ->where('academic_year_id', $academicYear->academic_year_id)
                ->where('from_class_id', $fromClass->class_id)
                ->get()
                ->sortBy('student.name');
        }

        return view('pages.admin.promotions.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'fromClass' => $fromClass,
            'pendingStudents' => $pendingStudents,
            'processed' => $processed,
            'totalProcessed' => $academicYear->promotions()->count(),
        ]);
    }

    public function preview(PromotionRequest $request): View
    {
        $plan = $request->plan();

        return view('pages.admin.promotions.preview', [
            'academicYear' => $request->academicYear(),
            'fromClass' => SchoolClass::findOrFail($request->integer('from_class_id')),
            'plan' => $plan,
            'decisions' => $request->input('decisions'),
            'summary' => $plan
                ->groupBy(fn (array $row): string => match ($row['result']) {
                    PromotionResult::Promoted => 'Naik ke '.$row['toClass']->class_name,
                    default => $row['result']->label(),
                })
                ->map->count(),
        ]);
    }

    /**
     * Apply the plan in one transaction and record a history row per student.
     */
    public function store(PromotionRequest $request): RedirectResponse
    {
        $academicYear = $request->academicYear();
        $plan = $request->plan();
        $fromClassId = $request->integer('from_class_id');

        try {
            DB::transaction(function () use ($plan, $academicYear, $fromClassId, $request): void {
                foreach ($plan as $row) {
                    /** @var Student $student */
                    $student = $row['student'];

                    StudentPromotion::create([
                        'academic_year_id' => $academicYear->academic_year_id,
                        'student_id' => $student->student_id,
                        'from_class_id' => $fromClassId,
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
            return redirect()
                ->route('admin.kenaikan.index', ['from_class_id' => $fromClassId])
                ->with('error', 'Sebagian siswa sudah diproses oleh permintaan lain. Tidak ada perubahan yang disimpan.');
        }

        $summary = $plan
            ->countBy(fn (array $row): string => $row['result']->label())
            ->map(fn (int $count, string $label): string => "{$count} {$label}")
            ->implode(', ');

        return redirect()
            ->route('admin.kenaikan.index', ['from_class_id' => $fromClassId])
            ->with('alert', [
                'icon' => 'success',
                'title' => 'Kenaikan kelas diproses',
                'text' => "{$plan->count()} siswa diproses untuk tahun ajaran {$academicYear->year_name}: {$summary}.",
                'toast' => false,
            ]);
    }
}
