<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubjectRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Step 4 of the yearly flow: subject + guru + class, bound to the active academic year.
 * Subjects of earlier years remain visible (year filter) but cannot be changed. Create/edit happen in the modal on the index page.
 */
class SubjectController extends Controller implements HasMiddleware
{
    use RespondsToModalForms;

    public static function middleware(): array
    {
        return [
            new Middleware('can:access-admin'),
            new Middleware('active-year:promoted', only: ['create', 'store']),
        ];
    }

    public function index(): View
    {
        $academicYears = AcademicYear::orderByDesc('year_name')->get();

        return view('pages.admin.subjects.index', [
            'academicYears' => $academicYears,
            'activeYear' => $academicYears->firstWhere('is_active', true),
            'classes' => SchoolClass::orderBy('class_name')->get(['class_id', 'class_name']),
            'teachers' => User::teachers()->orderBy('name')->get(['id', 'name']),
            'activeTeachers' => User::teachers()->active()->orderBy('name')->get(['id', 'name']),
            'subjectNames' => Subject::query()->distinct()->orderBy('subject_name')->pluck('subject_name'),
        ]);
    }

    /**
     * DataTables server-side endpoint. Filters: academic_year_id (default active), class_id, user_id.
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $academicYearId = $request->integer('academic_year_id') ?: AcademicYear::current()?->academic_year_id;

        $query = Subject::query()
            ->join('classes', 'classes.class_id', '=', 'subjects.class_id')
            ->join('users', 'users.id', '=', 'subjects.user_id')
            ->join('academic_years', 'academic_years.academic_year_id', '=', 'subjects.academic_year_id')
            ->select('subjects.*', 'classes.class_name', 'users.name as teacher_name', 'academic_years.year_name', 'academic_years.is_active as year_is_active')
            ->withCount('attendances')
            ->where('subjects.academic_year_id', $academicYearId)
            ->when($request->integer('class_id'), fn ($query, int $classId) => $query->where('subjects.class_id', $classId))
            ->when($request->integer('user_id'), fn ($query, int $userId) => $query->where('subjects.user_id', $userId));

        return $request->respond(
            query: $query,
            searchable: ['subjects.subject_name', 'classes.class_name', 'users.name'],
            sortable: [
                'subject_name' => 'subjects.subject_name',
                'class_name' => 'classes.class_name',
                'teacher_name' => 'users.name',
                'attendances_count' => 'attendances_count',
            ],
            transform: fn (Subject $subject): array => [
                'subject_name' => e($subject->subject_name),
                'class_name' => e($subject->class_name),
                'teacher_name' => e($subject->teacher_name),
                'year_name' => e($subject->year_name),
                'attendances_count' => $subject->attendances_count,
                'actions' => $subject->year_is_active
                    ? view('partials.datatable-actions', [
                        'modal' => 'subject-modal',
                        'editUrl' => route('admin.mapel.edit', $subject),
                        'deleteUrl' => route('admin.mapel.destroy', $subject),
                        'deleteMessage' => "Hapus mapel {$subject->subject_name} di {$subject->class_name}?",
                    ])->render()
                    : '<span class="badge">Histori</span>',
            ],
            defaultOrder: 'classes.class_name',
        );
    }

    /**
     * Payload for the create modal. The class checkboxes are fetched each time because a class
     * only becomes selectable once it has a homeroom teacher for the active year.
     */
    public function create(Request $request): JsonResponse|RedirectResponse
    {
        return $this->modalForm($request, [
            'slots' => [
                'class_checks' => view('pages.admin.subjects.class-checks', ['classes' => $this->classesWithHomeroomFlag($request)])->render(),
            ],
        ], route('admin.mapel.index'));
    }

    /**
     * One row per selected class, created together.
     */
    public function store(SubjectRequest $request): JsonResponse|RedirectResponse
    {
        $academicYear = $request->academicYear();
        $classIds = array_map('intval', $request->validated('class_ids'));

        DB::transaction(function () use ($request, $academicYear, $classIds): void {
            foreach ($classIds as $classId) {
                Subject::create([
                    'subject_name' => $request->validated('subject_name'),
                    'user_id' => $request->validated('user_id'),
                    'class_id' => $classId,
                    'academic_year_id' => $academicYear->academic_year_id,
                ]);
            }
        });

        $classNames = SchoolClass::whereKey($classIds)->orderBy('class_name')->pluck('class_name')->implode(', ');

        return $this->modalSaved($request, "Mapel {$request->validated('subject_name')} ditambahkan untuk {$classNames}.", route('admin.mapel.index'));
    }

    /**
     * Payload for the edit modal.
     */
    public function edit(Request $request, Subject $subject): JsonResponse|RedirectResponse
    {
        if ($denied = $this->denyHistoryChanges($request, $subject)) {
            return $denied;
        }

        return $this->modalForm($request, [
            'action' => route('admin.mapel.update', $subject),
            'method' => 'PUT',
            'title' => "Edit {$subject->subject_name} · {$subject->schoolClass->class_name}",
            'values' => [
                'subject_name' => $subject->subject_name,
                'user_id' => $subject->user_id,
                'class_id' => $subject->class_id,
            ],
            'slots' => [
                'class_options' => view('pages.admin.subjects.class-options', ['classes' => $this->classesWithHomeroomFlag($request)])->render(),
            ],
        ], route('admin.mapel.index'));
    }

    public function update(SubjectRequest $request, Subject $subject): JsonResponse|RedirectResponse
    {
        if ($denied = $this->denyHistoryChanges($request, $subject)) {
            return $denied;
        }

        if ((int) $request->validated('class_id') !== $subject->class_id && $subject->attendances()->exists()) {
            return $this->modalRejected($request, 'Kelas tidak bisa diubah karena mapel ini sudah memiliki data absensi.', route('admin.mapel.index'));
        }

        $subject->update($request->safe()->only(['subject_name', 'user_id', 'class_id']));

        return $this->modalSaved($request, "Mapel {$subject->subject_name} berhasil diperbarui.", route('admin.mapel.index'));
    }

    public function destroy(Request $request, Subject $subject): JsonResponse|RedirectResponse
    {
        if ($denied = $this->denyHistoryChanges($request, $subject)) {
            return $denied;
        }

        $attendanceCount = $subject->attendances()->count();

        if ($attendanceCount > 0) {
            return $this->modalRejected($request, "Mapel {$subject->subject_name} tidak bisa dihapus karena sudah memiliki {$attendanceCount} data absensi.", route('admin.mapel.index'));
        }

        $subject->delete();

        return $this->modalSaved($request, "Mapel {$subject->subject_name} di {$subject->schoolClass->class_name} dihapus.", route('admin.mapel.index'));
    }

    private function denyHistoryChanges(Request $request, Subject $subject): JsonResponse|RedirectResponse|null
    {
        if ($subject->academicYear->is_active) {
            return null;
        }

        return $this->modalRejected($request, "Mapel tahun ajaran {$subject->academicYear->year_name} adalah histori dan tidak bisa diubah.", route('admin.mapel.index'));
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function classesWithHomeroomFlag(Request $request): Collection
    {
        $academicYear = $request->attributes->get('activeAcademicYear') ?? AcademicYear::current();

        return SchoolClass::query()
            ->withExists(['homeroomTeachers as has_homeroom' => fn ($query) => $query->where('academic_year_id', $academicYear?->academic_year_id)])
            ->orderBy('class_name')
            ->get(['class_id', 'class_name']);
    }
}
