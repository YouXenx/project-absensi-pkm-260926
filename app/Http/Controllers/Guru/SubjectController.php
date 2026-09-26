<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataTableRequest;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Daily operation 1: subjects taught by the signed-in guru in the active academic year.
 */
class SubjectController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:access-guru')];
    }

    public function index(): View
    {
        return view('pages.guru.subjects.index', ['academicYear' => AcademicYear::current()]);
    }

    /**
     * DataTables endpoint. Always filtered by user_id = auth()->id() and the active academic year.
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::current();
        $today = now()->toDateString();
        $promotionCompleted = (bool) $academicYear?->promotionCompleted();

        $query = Subject::query()
            ->forTeacher($request->user())
            ->where('subjects.academic_year_id', $academicYear?->academic_year_id)
            ->join('classes', 'classes.class_id', '=', 'subjects.class_id')
            ->select('subjects.*', 'classes.class_name')
            ->selectSub(
                DB::table('homeroom_teachers')->selectRaw('count(*)')->whereColumn('homeroom_teachers.class_id', 'subjects.class_id')->whereColumn('homeroom_teachers.academic_year_id', 'subjects.academic_year_id'),
                'homeroom_count',
            )
            ->selectSub(
                DB::table('students')->selectRaw('count(*)')->whereColumn('students.class_id', 'subjects.class_id')->where('students.status', StudentStatus::Active->value),
                'active_students_count',
            )
            ->selectSub(
                DB::table('attendances')->selectRaw('count(*)')->whereColumn('attendances.subject_id', 'subjects.subject_id')->where('attendances.date', $today),
                'today_attendance_count',
            );

        return $request->respond(
            query: $query,
            searchable: ['subjects.subject_name', 'classes.class_name'],
            sortable: [
                'subject_name' => 'subjects.subject_name',
                'class_name' => 'classes.class_name',
                'active_students_count' => 'active_students_count',
                'today' => 'today_attendance_count',
            ],
            transform: function (Subject $subject) use ($promotionCompleted): array {
                // Same conditions as AttendancePolicy::take(), computed without a query per row.
                $lockedReason = match (true) {
                    ! $promotionCompleted => 'Kenaikan kelas tahun ajaran ini belum selesai diproses admin.',
                    (int) $subject->homeroom_count === 0 => 'Kelas ini belum punya wali kelas di tahun ajaran ini.',
                    default => null,
                };

                return [
                    'subject_name' => e($subject->subject_name),
                    'class_name' => e($subject->class_name),
                    'active_students_count' => (int) $subject->active_students_count,
                    'today' => view('pages.guru.subjects.today-status', [
                        'recorded' => (int) $subject->today_attendance_count,
                        'expected' => (int) $subject->active_students_count,
                        'lockedReason' => $lockedReason,
                    ])->render(),
                    'actions' => $lockedReason
                        ? ''
                        : '<a class="btn btn--primary" style="padding:5px 12px;font-size:12px" href="'.e(route('guru.absensi.index', ['subject_id' => $subject->subject_id])).'">Absen</a>',
                ];
            },
            defaultOrder: 'classes.class_name',
        );
    }
}
