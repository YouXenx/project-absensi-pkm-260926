<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\TakeAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Daily operations 2–5: pick a subject, see the class roster, record attendance one student at a time
 * or for the whole class, and correct today's entries. The date is always today.
 */
class AttendanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:access-guru')];
    }

    public function index(Request $request): View
    {
        $teacher = $request->user();
        $academicYear = AcademicYear::current();

        // Only subjects the yearly flow has opened (see AttendancePolicy::take) can be picked.
        $subjects = $academicYear?->promotionCompleted()
            ? Subject::query()->forTeacher($teacher)->withHomeroomTeacher()->with('schoolClass')->where('academic_year_id', $academicYear->academic_year_id)->get()->sortBy(['schoolClass.class_name', 'subject_name'])->values()
            : collect();
        $lockedSubjectCount = $academicYear
            ? Subject::query()->forTeacher($teacher)->where('academic_year_id', $academicYear->academic_year_id)->count() - $subjects->count()
            : 0;

        $subject = null;
        $students = collect();
        $recorded = collect();

        if ($request->filled('subject_id')) {
            $subject = Subject::with(['schoolClass', 'academicYear'])->findOrFail($request->integer('subject_id'));
            Gate::authorize('take-attendance', $subject);

            $students = $subject->schoolClass->activeStudents()->orderBy('name')->get();
            $recorded = Attendance::query()
                ->where('subject_id', $subject->subject_id)
                ->where('date', now()->toDateString())
                ->get()
                ->keyBy('student_id');
        }

        return view('pages.guru.attendance.index', [
            'academicYear' => $academicYear,
            'subjects' => $subjects,
            'lockedSubjectCount' => $lockedSubjectCount,
            'subject' => $subject,
            'students' => $students,
            'recorded' => $recorded,
            'today' => now(),
        ]);
    }

    /**
     * Bulk save for the whole class ("tandai semua hadir", then adjust).
     */
    public function store(TakeAttendanceRequest $request, Subject $subject): RedirectResponse
    {
        $rows = $request->rows();

        DB::transaction(fn () => $this->save($subject, $rows->all()));

        $counts = $rows->countBy('status');

        return redirect()
            ->route('guru.absensi.index', ['subject_id' => $subject->subject_id])
            ->with('alert', [
                'icon' => 'success',
                'title' => 'Absensi tersimpan',
                'text' => "{$subject->subject_name} {$subject->schoolClass->class_name}, ".now()->format('d/m/Y').': '
                    .collect(['hadir' => 'hadir', 'izin' => 'izin', 'sakit' => 'sakit', 'alpha' => 'alpha'])
                        ->map(fn (string $label, string $status): string => ($counts[$status] ?? 0)." {$label}")
                        ->implode(', ').'.',
                'toast' => false,
            ]);
    }

    /**
     * Save a single student's row.
     */
    public function storeOne(TakeAttendanceRequest $request, Subject $subject, Student $student): RedirectResponse
    {
        $this->save($subject, $request->rows()->only($student->student_id)->all());

        return redirect()
            ->route('guru.absensi.index', ['subject_id' => $subject->subject_id])
            ->with('success', "Absensi {$student->name} tersimpan.");
    }

    /**
     * Upsert today's record per student (one row per subject, student and date).
     *
     * @param  array<int, array{status: string, description: string|null}>  $rows
     */
    private function save(Subject $subject, array $rows): void
    {
        $today = now()->toDateString();

        foreach ($rows as $studentId => $row) {
            Attendance::updateOrCreate(
                ['subject_id' => $subject->subject_id, 'student_id' => $studentId, 'date' => $today],
                ['status' => $row['status'], 'description' => $row['description']],
            );
        }
    }
}
