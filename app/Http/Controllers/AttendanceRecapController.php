<?php

namespace App\Http\Controllers;

use App\AttendanceStatus;
use App\Exports\AttendanceSheetExport;
use App\Http\Requests\AttendanceRecapRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attendance recap per student, filtered by academic year, semester/date range, class and subject.
 * Admins see everything; a guru only ever sees subjects they teach (Attendance::visibleTo / Subject::forTeacher),
 * whatever ids are put in the query string.
 */
class AttendanceRecapController extends Controller
{
    public function index(AttendanceRecapRequest $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        if (! $filters['selectedYear']) {
            return view('pages.recap.index', ['academicYears' => $filters['academicYears'], 'selectedYear' => null, 'routeName' => $this->routeName($user)]);
        }

        $rows = $this->recapRows($user, $filters['selectedYear'], $filters['dateFrom'], $filters['dateTo'], $filters['selectedClass'], $filters['selectedSubject']);

        return view('pages.recap.index', [
            ...$filters,
            'routeName' => $this->routeName($user),
            'sheetRoute' => $this->sheetRouteName($user),
            'subjects' => $filters['subjects']->when($filters['selectedClass'], fn (Collection $subjects) => $subjects->where('class_id', $filters['selectedClass']->class_id)),
            'rows' => $rows,
            'totals' => collect(AttendanceStatus::cases())
                ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => (int) $rows->sum($status->value)])
                ->put('total', (int) $rows->sum('total')),
        ]);
    }

    /**
     * The same recap as an Excel workbook in the school's "Daftar Hadir Siswa" layout: one sheet per class and
     * month, a mark per date. Takes the same filters as the page, and the same visibility rules apply.
     */
    public function sheet(AttendanceRecapRequest $request): StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $back = redirect()->route($this->routeName($user), $request->query());

        if (! $filters['selectedYear']) {
            return $back->with('error', 'Belum ada tahun ajaran untuk diekspor.');
        }

        $subject = $filters['selectedSubject'];
        // A subject belongs to one class, so choosing only the subject (Rekap per Mapel) still gives one class.
        $class = $filters['selectedClass'] ?? $subject?->schoolClass;
        $records = $this->sheetRecords($user, $filters['selectedYear'], $filters['dateFrom'], $filters['dateTo'], $class, $subject);

        if ($records->isEmpty()) {
            return $back->with('error', 'Belum ada absensi pada periode ini untuk diekspor.');
        }

        $spreadsheet = (new AttendanceSheetExport($filters['selectedYear'], $filters['dateFrom'], $filters['dateTo'], $subject))->build($records);
        $scope = ($class?->class_name ?? 'Semua Kelas').($subject ? ' '.$subject->subject_name : '');
        $filename = 'Daftar Hadir '.preg_replace('~[\\\\/:*?"<>|]+~', '-', $scope).' '.$filters['dateFrom']->format('Y-m-d').' sd '.$filters['dateTo']->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            fn () => AttendanceSheetExport::write($spreadsheet),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * The filters of the recap, resolved against what this user may see. Ids from the query string that are
     * not visible to the user simply do not match anything.
     *
     * @return array{
     *     academicYears: Collection<int, AcademicYear>, selectedYear: AcademicYear|null, semester?: string,
     *     dateFrom?: CarbonImmutable, dateTo?: CarbonImmutable, classes?: Collection<int, SchoolClass>,
     *     subjects?: Collection<int, Subject>, selectedClass?: SchoolClass|null, selectedSubject?: Subject|null,
     * }
     */
    private function filters(AttendanceRecapRequest $request): array
    {
        $user = $request->user();

        $academicYears = AcademicYear::query()
            ->when($user->isGuru(), fn (Builder $query) => $query->whereHas('subjects', fn (Builder $query) => $query->forTeacher($user)))
            ->orderByDesc('year_name')
            ->get();

        $selectedYear = $academicYears->firstWhere('academic_year_id', $request->integer('academic_year_id'))
            ?? $academicYears->firstWhere('is_active', true)
            ?? $academicYears->first();

        if (! $selectedYear) {
            return ['academicYears' => $academicYears, 'selectedYear' => null];
        }

        $semester = $request->input('semester', (string) $selectedYear->currentSemester());
        [$dateFrom, $dateTo] = $semester === AttendanceRecapRequest::CUSTOM_RANGE
            ? [CarbonImmutable::parse($request->input('date_from')), CarbonImmutable::parse($request->input('date_to'))]
            : $selectedYear->semesterRange((int) $semester);

        $subjects = $this->visibleSubjects($user, $selectedYear);
        $classes = SchoolClass::query()->whereKey($subjects->pluck('class_id')->unique())->orderBy('class_name')->get();

        $selectedClass = $classes->firstWhere('class_id', $request->integer('class_id'));
        $selectedSubject = $subjects
            ->when($selectedClass, fn (Collection $subjects) => $subjects->where('class_id', $selectedClass->class_id))
            ->firstWhere('subject_id', $request->integer('subject_id'));

        return [
            'academicYears' => $academicYears,
            'selectedYear' => $selectedYear,
            'semester' => $semester,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'classes' => $classes,
            'subjects' => $subjects,
            'selectedClass' => $selectedClass,
            'selectedSubject' => $selectedSubject,
        ];
    }

    /**
     * @return Collection<int, Subject>
     */
    private function visibleSubjects(User $user, AcademicYear $academicYear): Collection
    {
        return Subject::query()
            ->with('schoolClass')
            ->where('academic_year_id', $academicYear->academic_year_id)
            ->when($user->isGuru(), fn (Builder $query) => $query->forTeacher($user))
            ->orderBy('subject_name')
            ->get();
    }

    /**
     * One row per student per class (the class of the subject, so old years stay correct after promotion).
     *
     * @return Collection<int, object>
     */
    private function recapRows(User $user, AcademicYear $academicYear, CarbonImmutable $dateFrom, CarbonImmutable $dateTo, ?SchoolClass $class, ?Subject $subject): Collection
    {
        $statusColumns = collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $status): string => "sum(case when attendances.status = '{$status->value}' then 1 else 0 end) as {$status->value}")
            ->implode(', ');

        return Attendance::query()
            ->visibleTo($user)
            ->join('subjects', 'subjects.subject_id', '=', 'attendances.subject_id')
            ->join('students', 'students.student_id', '=', 'attendances.student_id')
            ->join('classes', 'classes.class_id', '=', 'subjects.class_id')
            ->where('subjects.academic_year_id', $academicYear->academic_year_id)
            // Half-open range so the last day is included whether the column holds a date or a datetime.
            ->where('attendances.date', '>=', $dateFrom->toDateString())
            ->where('attendances.date', '<', $dateTo->addDay()->toDateString())
            ->when($class, fn (Builder $query) => $query->where('subjects.class_id', $class->class_id))
            ->when($subject, fn (Builder $query) => $query->where('attendances.subject_id', $subject->subject_id))
            ->groupBy('students.student_id', 'students.nis', 'students.name', 'students.status', 'classes.class_id', 'classes.class_name')
            ->orderBy('classes.class_name')
            ->orderBy('students.name')
            ->selectRaw("students.student_id, students.nis, students.name, students.status, classes.class_name, {$statusColumns}, count(*) as total")
            ->toBase()
            ->get();
    }

    /**
     * Every attendance record in scope, with what the sheet needs of the student. The class is the class of the
     * subject, like in the recap, so an old year keeps its classes after promotion.
     *
     * @return Collection<int, object>
     */
    private function sheetRecords(User $user, AcademicYear $academicYear, CarbonImmutable $dateFrom, CarbonImmutable $dateTo, ?SchoolClass $class, ?Subject $subject): Collection
    {
        return Attendance::query()
            ->visibleTo($user)
            ->join('subjects', 'subjects.subject_id', '=', 'attendances.subject_id')
            ->join('students', 'students.student_id', '=', 'attendances.student_id')
            ->join('classes', 'classes.class_id', '=', 'subjects.class_id')
            ->where('subjects.academic_year_id', $academicYear->academic_year_id)
            ->where('attendances.date', '>=', $dateFrom->toDateString())
            ->where('attendances.date', '<', $dateTo->addDay()->toDateString())
            ->when($class, fn (Builder $query) => $query->where('subjects.class_id', $class->class_id))
            ->when($subject, fn (Builder $query) => $query->where('attendances.subject_id', $subject->subject_id))
            ->select([
                'students.student_id', 'students.nis', 'students.name', 'students.gender',
                'classes.class_id', 'classes.class_name', 'attendances.date', 'attendances.status',
            ])
            ->toBase()
            ->get();
    }

    private function routeName(User $user): string
    {
        return $user->isAdmin() ? 'admin.laporan.index' : 'guru.rekap.index';
    }

    private function sheetRouteName(User $user): string
    {
        return $user->isAdmin() ? 'admin.laporan.daftar-hadir' : 'guru.rekap.daftar-hadir';
    }
}
