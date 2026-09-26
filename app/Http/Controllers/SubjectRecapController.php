<?php

namespace App\Http\Controllers;

use App\AttendanceStatus;
use App\Http\Requests\AttendanceRecapRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Attendance recap for one subject: the percentage per student, plus how many meetings were recorded.
 * Admins pick any subject; a guru only ever sees their own (Subject::forTeacher).
 */
class SubjectRecapController extends Controller
{
    public function index(AttendanceRecapRequest $request): View
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
            return view('pages.recap.subject', [
                'routeName' => $this->routeName($user),
                'academicYears' => $academicYears,
                'selectedYear' => null,
            ]);
        }

        $subjects = Subject::query()
            ->with(['schoolClass', 'teacher'])
            ->where('academic_year_id', $selectedYear->academic_year_id)
            ->when($user->isGuru(), fn (Builder $query) => $query->forTeacher($user))
            ->get()
            ->sortBy([fn (Subject $a, Subject $b): int => [$a->schoolClass->class_name, $a->subject_name] <=> [$b->schoolClass->class_name, $b->subject_name]])
            ->values();

        $selectedSubject = $subjects->firstWhere('subject_id', $request->integer('subject_id')) ?? $subjects->first();

        $semester = $request->input('semester', (string) $selectedYear->currentSemester());
        [$dateFrom, $dateTo] = $semester === AttendanceRecapRequest::CUSTOM_RANGE
            ? [CarbonImmutable::parse($request->input('date_from')), CarbonImmutable::parse($request->input('date_to'))]
            : $selectedYear->semesterRange((int) $semester);

        $rows = $selectedSubject ? $this->rows($user, $selectedSubject, $dateFrom, $dateTo) : collect();
        $meetings = $selectedSubject ? $this->meetings($user, $selectedSubject, $dateFrom, $dateTo) : 0;

        return view('pages.recap.subject', [
            'routeName' => $this->routeName($user),
            'academicYears' => $academicYears,
            'selectedYear' => $selectedYear,
            'semester' => $semester,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'subjects' => $subjects,
            'selectedSubject' => $selectedSubject,
            'rows' => $rows,
            'meetings' => $meetings,
            'totals' => collect(AttendanceStatus::cases())
                ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => (int) $rows->sum($status->value)])
                ->put('total', (int) $rows->sum('total')),
            'averageAttendance' => $rows->isEmpty() || $rows->sum('total') === 0
                ? 0.0
                : round($rows->sum(AttendanceStatus::Present->value) / $rows->sum('total') * 100, 1),
        ]);
    }

    /**
     * One row per student who has attendance for this subject in the range.
     *
     * @return Collection<int, object>
     */
    private function rows(User $user, Subject $subject, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): Collection
    {
        $statusColumns = collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $status): string => "sum(case when attendances.status = '{$status->value}' then 1 else 0 end) as {$status->value}")
            ->implode(', ');

        return $this->scopedQuery($user, $subject, $dateFrom, $dateTo)
            ->join('students', 'students.student_id', '=', 'attendances.student_id')
            ->groupBy('students.student_id', 'students.nis', 'students.name', 'students.status')
            ->orderBy('students.name')
            ->selectRaw("students.student_id, students.nis, students.name, students.status, {$statusColumns}, count(*) as total")
            ->toBase()
            ->get();
    }

    /**
     * Distinct days on which this subject was recorded.
     */
    private function meetings(User $user, Subject $subject, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): int
    {
        return $this->scopedQuery($user, $subject, $dateFrom, $dateTo)->distinct()->count('attendances.date');
    }

    /**
     * @return Builder<Attendance>
     */
    private function scopedQuery(User $user, Subject $subject, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): Builder
    {
        return Attendance::query()
            ->visibleTo($user)
            ->where('attendances.subject_id', $subject->subject_id)
            ->where('attendances.date', '>=', $dateFrom->toDateString())
            ->where('attendances.date', '<', $dateTo->addDay()->toDateString());
    }

    private function routeName(User $user): string
    {
        return $user->isAdmin() ? 'admin.laporan.mapel' : 'guru.rekap.mapel';
    }
}
