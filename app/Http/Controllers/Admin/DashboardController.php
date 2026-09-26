<?php

namespace App\Http\Controllers\Admin;

use App\AttendanceStatus;
use App\Gender;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\StudentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class DashboardController extends Controller implements HasMiddleware
{
    public const ATTENDANCE_CHART_WEEKS = 8;

    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    /**
     * School-wide statistics. Student figures count active (not graduated) students only.
     */
    public function __invoke(): View
    {
        $teacherCounts = User::teachers()
            ->selectRaw('count(*) as total, sum(case when is_active = 1 then 1 else 0 end) as active')
            ->first();

        $genderCounts = Student::query()
            ->active()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $classes = SchoolClass::query()
            ->withCount(['students' => fn ($query) => $query->active()])
            ->orderBy('class_name')
            ->get();

        $totalStudents = (int) $genderCounts->sum();
        $academicYear = AcademicYear::current();

        return view('pages.admin.dashboard', [
            'academicYear' => $academicYear,
            'yearProgress' => $academicYear?->setupProgress(),
            'graduatedStudents' => Student::query()->where('status', StudentStatus::Graduated)->count(),
            'stats' => [
                'teachers' => (int) $teacherCounts->total,
                'activeTeachers' => (int) $teacherCounts->active,
                'classes' => $classes->count(),
                'emptyClasses' => $classes->where('students_count', 0)->count(),
                'students' => $totalStudents,
                'maleStudents' => (int) $genderCounts->get(Gender::Male->value, 0),
                'femaleStudents' => (int) $genderCounts->get(Gender::Female->value, 0),
                'averagePerClass' => $classes->isEmpty() ? 0 : round($totalStudents / $classes->count(), 1),
            ],
            'classes' => $classes,
            'recentStudents' => Student::with('schoolClass')->latest('student_id')->limit(5)->get(),
            'recentTeachers' => User::teachers()->latest('id')->limit(5)->get(),
            'classChart' => [
                'labels' => $classes->pluck('class_name')->values(),
                'datasets' => [['label' => 'Siswa aktif', 'data' => $classes->pluck('students_count')->values(), 'color' => '--primary']],
                'unit' => 'siswa',
            ],
            'attendanceChart' => $this->weeklyAttendance(),
        ]);
    }

    /**
     * Attendance per status per week for the last 8 weeks that have records (up to today), for a stacked bar chart.
     * Anchored on the latest recorded day, so a school holiday does not leave the chart empty.
     *
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string}>, stacked: bool, unit: string, from: string, to: string, total: int}|null
     */
    private function weeklyAttendance(): ?array
    {
        $latest = Attendance::query()->where('date', '<=', now()->toDateString())->max('date');

        if ($latest === null) {
            return null;
        }

        $lastWeek = CarbonImmutable::parse($latest)->startOfWeek();
        $firstWeek = $lastWeek->subWeeks(self::ATTENDANCE_CHART_WEEKS - 1);
        $weeks = collect(range(0, self::ATTENDANCE_CHART_WEEKS - 1))->map(fn (int $offset): CarbonImmutable => $firstWeek->addWeeks($offset));

        $perDay = Attendance::query()
            ->where('date', '>=', $firstWeek->toDateString())
            ->where('date', '<=', $lastWeek->endOfWeek()->toDateString())
            ->selectRaw('date, status, count(*) as total')
            ->groupBy('date', 'status')
            ->toBase()
            ->get();

        $colors = [
            AttendanceStatus::Present->value => '--success',
            AttendanceStatus::Permission->value => '--info',
            AttendanceStatus::Sick->value => '--orange',
            AttendanceStatus::Absent->value => '--danger',
        ];

        return [
            'labels' => $weeks->map(fn (CarbonImmutable $week): string => $week->format('d/m'))->values()->all(),
            'datasets' => collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $status): array => [
                'label' => $status->label(),
                'color' => $colors[$status->value],
                'data' => $weeks->map(fn (CarbonImmutable $week): int => (int) $perDay
                    ->where('status', $status->value)
                    ->filter(fn (object $row): bool => CarbonImmutable::parse($row->date)->startOfWeek()->equalTo($week))
                    ->sum('total'))->values()->all(),
            ])->all(),
            'stacked' => true,
            'unit' => 'catatan',
            'from' => $firstWeek->format('d/m/Y'),
            'to' => $lastWeek->endOfWeek()->format('d/m/Y'),
            'total' => (int) $perDay->sum('total'),
        ];
    }
}
