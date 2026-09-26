<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeroomTeacherRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\AcademicYear;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\User;
use App\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Step 3 of the yearly flow. Assignments are always created for the active academic year;
 * assignments of earlier years are shown read-only as history. Create/edit happen in the modal on the index page.
 */
class HomeroomTeacherController extends Controller implements HasMiddleware
{
    use RespondsToModalForms;

    public static function middleware(): array
    {
        return [
            new Middleware('can:access-admin'),
            new Middleware('active-year:promoted', only: ['create', 'store']),
        ];
    }

    public function index(Request $request): View
    {
        $academicYears = AcademicYear::orderByDesc('year_name')->get();

        return view('pages.admin.homerooms.index', [
            'academicYears' => $academicYears,
            'selectedYear' => $academicYears->firstWhere('academic_year_id', $request->integer('academic_year_id'))
                ?? $academicYears->firstWhere('is_active', true)
                ?? $academicYears->first(),
            'activeYear' => $academicYears->firstWhere('is_active', true),
            'teachers' => User::teachers()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * DataTables endpoint: every class with its homeroom teacher for academic_year_id (default: active year).
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::find($request->integer('academic_year_id')) ?? AcademicYear::current();
        $academicYearId = $academicYear?->academic_year_id;

        $query = SchoolClass::query()
            ->leftJoin('homeroom_teachers', fn ($join) => $join
                ->on('homeroom_teachers.class_id', '=', 'classes.class_id')
                ->where('homeroom_teachers.academic_year_id', $academicYearId))
            ->leftJoin('users', 'users.id', '=', 'homeroom_teachers.user_id')
            ->select('classes.*', 'homeroom_teachers.homeroom_id', 'users.name as teacher_name')
            ->selectSub(
                DB::table('students')->selectRaw('count(*)')->whereColumn('students.class_id', 'classes.class_id')->where('students.status', StudentStatus::Active->value),
                'active_students_count',
            );

        return $request->respond(
            query: $query,
            searchable: ['classes.class_name', 'users.name'],
            sortable: [
                'class_name' => 'classes.class_name',
                'active_students_count' => 'active_students_count',
                'teacher_name' => 'users.name',
            ],
            transform: fn (SchoolClass $schoolClass): array => [
                'class_name' => e($schoolClass->class_name),
                'active_students_count' => (int) $schoolClass->active_students_count,
                'teacher_name' => match (true) {
                    $schoolClass->teacher_name !== null => e($schoolClass->teacher_name),
                    $schoolClass->active_students_count > 0 => '<span class="badge warning dot">Belum ada wali</span>',
                    default => '<span class="cell-date">—</span>',
                },
                'actions' => view('pages.admin.homerooms.actions', [
                    'schoolClass' => $schoolClass,
                    'isEditable' => (bool) $academicYear?->is_active,
                ])->render(),
            ],
            defaultOrder: 'classes.class_name',
        );
    }

    /**
     * Payload for the create modal; ?class_id= preselects the class of the clicked row.
     */
    public function create(Request $request): JsonResponse|RedirectResponse
    {
        return $this->modalForm($request, [
            'values' => ['class_id' => $request->integer('class_id') ?: ''],
            'slots' => ['class_options' => $this->classOptions($request)],
        ], route('admin.wali-kelas.index'));
    }

    public function store(HomeroomTeacherRequest $request): JsonResponse|RedirectResponse
    {
        $homeroom = HomeroomTeacher::create([
            ...$request->validated(),
            'academic_year_id' => $request->academicYear()->academic_year_id,
        ]);

        return $this->modalSaved($request, "{$homeroom->teacher->name} ditetapkan sebagai wali {$homeroom->schoolClass->class_name}.", route('admin.wali-kelas.index'));
    }

    /**
     * Payload for the edit modal.
     */
    public function edit(Request $request, HomeroomTeacher $homeroomTeacher): JsonResponse|RedirectResponse
    {
        if ($denied = $this->denyHistoryChanges($request, $homeroomTeacher)) {
            return $denied;
        }

        return $this->modalForm($request, [
            'action' => route('admin.wali-kelas.update', $homeroomTeacher),
            'method' => 'PUT',
            'title' => "Ganti Wali {$homeroomTeacher->schoolClass->class_name}",
            'values' => [
                'class_id' => $homeroomTeacher->class_id,
                'user_id' => $homeroomTeacher->user_id,
            ],
            'slots' => ['class_options' => $this->classOptions($request, $homeroomTeacher)],
        ], route('admin.wali-kelas.index'));
    }

    public function update(HomeroomTeacherRequest $request, HomeroomTeacher $homeroomTeacher): JsonResponse|RedirectResponse
    {
        if ($denied = $this->denyHistoryChanges($request, $homeroomTeacher)) {
            return $denied;
        }

        $homeroomTeacher->update($request->validated());

        return $this->modalSaved($request, "Wali {$homeroomTeacher->schoolClass->class_name} diperbarui menjadi {$homeroomTeacher->teacher->name}.", route('admin.wali-kelas.index'));
    }

    public function destroy(Request $request, HomeroomTeacher $homeroomTeacher): JsonResponse|RedirectResponse
    {
        if ($denied = $this->denyHistoryChanges($request, $homeroomTeacher)) {
            return $denied;
        }

        $subjectCount = $homeroomTeacher->schoolClass->subjects()->where('academic_year_id', $homeroomTeacher->academic_year_id)->count();

        if ($subjectCount > 0) {
            return $this->modalRejected($request, "Wali {$homeroomTeacher->schoolClass->class_name} tidak bisa dihapus karena kelas ini sudah punya {$subjectCount} mapel (absensinya akan terkunci). Ganti wali lewat tombol Edit.", route('admin.wali-kelas.index'));
        }

        $homeroomTeacher->delete();

        return $this->modalSaved($request, "Penugasan wali {$homeroomTeacher->schoolClass->class_name} dihapus.", route('admin.wali-kelas.index'));
    }

    /**
     * Only assignments of the active academic year may change.
     */
    private function denyHistoryChanges(Request $request, HomeroomTeacher $homeroomTeacher): JsonResponse|RedirectResponse|null
    {
        if ($homeroomTeacher->academicYear->is_active) {
            return null;
        }

        return $this->modalRejected(
            $request,
            "Data tahun ajaran {$homeroomTeacher->academicYear->year_name} adalah histori dan tidak bisa diubah.",
            route('admin.wali-kelas.index', ['academic_year_id' => $homeroomTeacher->academic_year_id]),
        );
    }

    /**
     * Class <option>s for the active year: classes that already have another homeroom teacher are disabled.
     */
    private function classOptions(Request $request, ?HomeroomTeacher $homeroomTeacher = null): string
    {
        $academicYear = $request->attributes->get('activeAcademicYear') ?? AcademicYear::current();

        return view('pages.admin.homerooms.class-options', [
            'homeroom' => $homeroomTeacher,
            'classes' => SchoolClass::query()
                ->with(['homeroomTeachers' => fn ($query) => $query->with('teacher')->where('academic_year_id', $academicYear?->academic_year_id)])
                ->orderBy('class_name')
                ->get(),
        ])->render();
    }
}
