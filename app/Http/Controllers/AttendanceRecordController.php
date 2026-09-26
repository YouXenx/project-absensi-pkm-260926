<?php

namespace App\Http\Controllers;

use App\AttendanceStatus;
use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Requests\AttendanceUpdateRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Attendance records, one row per student per subject per day.
 *
 *  - Guru ("Riwayat Absensi"): own subjects only, filtered by subject and a single date; edit only today.
 *  - Admin ("Koreksi Absensi"): every record, filtered by year, class, subject, date range and status;
 *    may edit or delete records of any date.
 *
 * Visibility comes from Attendance::visibleTo(); edit/delete rights from AttendancePolicy.
 * Editing happens in the modal on the index page; the summary cards refresh with the table.
 */
class AttendanceRecordController extends Controller
{
    use RespondsToModalForms;

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        return view('pages.attendance-records.index', [
            'routes' => $this->routeNames($user),
            'filters' => $filters,
            'summary' => $this->summary($user, $filters),
            'academicYears' => AcademicYear::orderByDesc('year_name')->get(),
            'classes' => SchoolClass::orderBy('class_name')->get(['class_id', 'class_name']),
            'subjects' => $this->subjectOptions($user, $filters),
        ]);
    }

    /**
     * DataTables server-side endpoint, using the same filters as the page (passed in the query string).
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $user = $request->user();
        $routes = $this->routeNames($user);
        $filters = $this->filters($request);

        $query = $this->filteredQuery($user, $filters)
            ->join('students', 'students.student_id', '=', 'attendances.student_id')
            ->select('attendances.*', 'subjects.subject_name', 'subjects.user_id', 'subjects.academic_year_id', 'classes.class_name', 'students.nis', 'students.name as student_name', 'academic_years.is_active as year_is_active');

        return $request->respond(
            query: $query,
            searchable: ['students.nis', 'students.name', 'subjects.subject_name', 'classes.class_name'],
            sortable: [
                'date' => 'attendances.date',
                'subject' => 'subjects.subject_name',
                'nis' => 'students.nis',
                'student_name' => 'students.name',
                'status' => 'attendances.status',
            ],
            transform: fn (Attendance $attendance): array => [
                'date' => $attendance->date->format('d/m/Y'),
                'subject' => e($attendance->subject_name).' <span class="cell-date">· '.e($attendance->class_name).'</span>',
                'nis' => e($attendance->nis),
                'student_name' => e($attendance->student_name),
                'status' => view('partials.attendance-status', ['status' => $attendance->status])->render(),
                'description' => e($attendance->description ?? '—'),
                'actions' => view('pages.attendance-records.actions', [
                    'attendance' => $attendance,
                    'routes' => $routes,
                    'canUpdate' => $user->can('update', $attendance),
                    'canDelete' => $user->can('delete', $attendance),
                ])->render(),
            ],
            defaultOrder: 'students.name',
            fragments: [
                '[data-fragment="attendance-summary"]' => view('pages.attendance-records.summary', [
                    'summary' => $this->summary($user, $filters),
                    'filters' => $filters,
                ])->render(),
            ],
        );
    }

    /**
     * Payload for the edit modal. A past-date correction by an admin asks for confirmation before saving.
     */
    public function edit(Request $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $attendance);

        $attendance->load(['subject.schoolClass', 'subject.teacher', 'student']);
        $isCorrection = $request->user()->isAdmin() && ! $attendance->isToday();
        $date = $attendance->date->format('d/m/Y');

        return $this->modalForm($request, [
            'action' => route($this->routeNames($request->user())['update'], $attendance),
            'method' => 'PUT',
            'title' => ($isCorrection ? 'Koreksi' : 'Edit')." absensi {$attendance->student->name}",
            'confirm' => $isCorrection ? "Simpan koreksi absensi {$attendance->student->name} tanggal {$date}?" : null,
            'values' => [
                'status' => $attendance->status->value,
                'description' => $attendance->description,
                'context' => "{$attendance->subject->subject_name} · {$attendance->subject->schoolClass->class_name} · {$date}",
                'note' => $isCorrection
                    ? "Koreksi tanggal lampau oleh admin. Guru pengampu: {$attendance->subject->teacher->name}."
                    : 'Absensi hari ini masih bisa diubah.',
            ],
        ], route($this->routeNames($request->user())['index'], $this->backToFilters($attendance, $request->user())));
    }

    public function update(AttendanceUpdateRequest $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        $attendance->update($request->validated());

        return $this->modalSaved(
            $request,
            "Absensi {$attendance->student->name} ({$attendance->date->format('d/m/Y')}) diperbarui menjadi {$attendance->status->label()}.",
            route($this->routeNames($request->user())['index'], $this->backToFilters($attendance, $request->user())),
        );
    }

    public function destroy(Request $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $attendance);

        $attendance->delete();

        return $this->modalSaved(
            $request,
            "Absensi {$attendance->student->name} tanggal {$attendance->date->format('d/m/Y')} dihapus.",
            route($this->routeNames($request->user())['index'], $this->backToFilters($attendance, $request->user())),
        );
    }

    /**
     * Record count per status for the current filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, int>
     */
    private function summary(User $user, array $filters): Collection
    {
        $totals = $this->filteredQuery($user, $filters)
            ->selectRaw('attendances.status, count(*) as total')
            ->groupBy('attendances.status')
            ->pluck('total', 'status');

        return collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => (int) ($totals[$status->value] ?? 0)]);
    }

    /**
     * @return array{academic_year_id: int|null, class_id: int|null, subject_id: int|null, status: string|null, date_from: CarbonImmutable, date_to: CarbonImmutable}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:'.implode(',', array_column(AttendanceStatus::cases(), 'value'))],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $today = CarbonImmutable::today();

        // A guru looks at one day at a time; an admin can correct a whole range.
        [$from, $to] = $request->user()->isGuru()
            ? array_fill(0, 2, isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : $today)
            : [
                isset($validated['date_from']) ? CarbonImmutable::parse($validated['date_from']) : $today,
                isset($validated['date_to']) ? CarbonImmutable::parse($validated['date_to']) : $today,
            ];

        return [
            'academic_year_id' => $request->user()->isAdmin() && isset($validated['academic_year_id']) ? (int) $validated['academic_year_id'] : null,
            'class_id' => $request->user()->isAdmin() && isset($validated['class_id']) ? (int) $validated['class_id'] : null,
            'subject_id' => isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
            'status' => $validated['status'] ?? null,
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Attendance>
     */
    private function filteredQuery(User $user, array $filters): Builder
    {
        return Attendance::query()
            ->visibleTo($user)
            ->join('subjects', 'subjects.subject_id', '=', 'attendances.subject_id')
            ->join('classes', 'classes.class_id', '=', 'subjects.class_id')
            ->join('academic_years', 'academic_years.academic_year_id', '=', 'subjects.academic_year_id')
            ->where('attendances.date', '>=', $filters['date_from']->toDateString())
            ->where('attendances.date', '<=', $filters['date_to']->toDateString())
            ->when($filters['academic_year_id'], fn (Builder $query, int $id) => $query->where('subjects.academic_year_id', $id))
            ->when($filters['class_id'], fn (Builder $query, int $id) => $query->where('subjects.class_id', $id))
            ->when($filters['subject_id'], fn (Builder $query, int $id) => $query->where('attendances.subject_id', $id))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('attendances.status', $status));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Subject>
     */
    private function subjectOptions(User $user, array $filters): Collection
    {
        return Subject::query()
            ->with(['schoolClass', 'academicYear'])
            ->when($user->isGuru(), fn (Builder $query) => $query->forTeacher($user))
            ->when($filters['academic_year_id'], fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($filters['class_id'], fn (Builder $query, int $id) => $query->where('class_id', $id))
            ->when($user->isAdmin() && ! $filters['academic_year_id'] && ! $filters['class_id'], fn (Builder $query) => $query->whereHas('academicYear', fn (Builder $query) => $query->where('is_active', true)))
            ->get()
            ->sortBy([fn (Subject $a, Subject $b): int => [$b->academicYear->year_name, $a->schoolClass->class_name, $a->subject_name] <=> [$a->academicYear->year_name, $b->schoolClass->class_name, $b->subject_name]])
            ->values();
    }

    /**
     * @return array{index: string, data: string, edit: string, update: string, destroy: string|null}
     */
    private function routeNames(User $user): array
    {
        $prefix = $user->isAdmin() ? 'admin.absensi' : 'guru.riwayat';

        return [
            'index' => "{$prefix}.index",
            'data' => "{$prefix}.data",
            'edit' => "{$prefix}.edit",
            'update' => "{$prefix}.update",
            'destroy' => $user->isAdmin() ? "{$prefix}.destroy" : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function backToFilters(Attendance $attendance, User $user): array
    {
        $date = $attendance->date->toDateString();

        return $user->isAdmin()
            ? ['subject_id' => $attendance->subject_id, 'date_from' => $date, 'date_to' => $date]
            : ['subject_id' => $attendance->subject_id, 'date' => $date];
    }
}
