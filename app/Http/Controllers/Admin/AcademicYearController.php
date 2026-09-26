<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicYearRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Step 1 of the yearly flow. Years are never edited or deleted here: old years are history.
 * A new year is created in the modal on the index page.
 */
class AcademicYearController extends Controller implements HasMiddleware
{
    use RespondsToModalForms;

    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    public function index(): View
    {
        $current = AcademicYear::current();

        return view('pages.admin.academic-years.index', [
            'current' => $current,
            'progress' => $current?->setupProgress(),
        ]);
    }

    /**
     * DataTables endpoint for every academic year. The "active year" checklist next to the table is
     * sent along as a fragment, so it is refreshed together with the table after a new year is created.
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $current = AcademicYear::current();

        $query = AcademicYear::query()
            ->withCount(['homeroomTeachers', 'subjects', 'promotions'])
            ->selectSub(
                DB::table('attendances')
                    ->join('subjects', 'subjects.subject_id', '=', 'attendances.subject_id')
                    ->whereColumn('subjects.academic_year_id', 'academic_years.academic_year_id')
                    ->selectRaw('count(*)'),
                'attendances_count',
            );

        return $request->respond(
            query: $query,
            searchable: ['year_name'],
            sortable: [
                'year_name' => 'year_name',
                'homeroom_teachers_count' => 'homeroom_teachers_count',
                'subjects_count' => 'subjects_count',
                'promotions_count' => 'promotions_count',
                'attendances_count' => 'attendances_count',
            ],
            transform: fn (AcademicYear $year): array => [
                'year_name' => e($year->year_name),
                'status' => $year->is_active ? '<span class="badge success dot">Aktif</span>' : '<span class="badge">Histori</span>',
                'homeroom_teachers_count' => '<a href="'.e(route('admin.wali-kelas.index', ['academic_year_id' => $year->academic_year_id])).'">'.$year->homeroom_teachers_count.'</a>',
                'subjects_count' => '<a href="'.e(route('admin.mapel.index', ['academic_year_id' => $year->academic_year_id])).'">'.$year->subjects_count.'</a>',
                'promotions_count' => $year->promotions_count,
                'attendances_count' => '<a href="'.e(route('admin.laporan.index', ['academic_year_id' => $year->academic_year_id])).'">'.number_format((int) $year->attendances_count, 0, ',', '.').'</a>',
                'actions' => view('pages.admin.academic-years.actions', ['year' => $year])->render(),
            ],
            defaultOrder: 'year_name',
            fragments: [
                '[data-fragment="current-year"]' => view('pages.admin.academic-years.current', [
                    'current' => $current,
                    'progress' => $current?->setupProgress(),
                ])->render(),
            ],
        );
    }

    /**
     * A year is only removed while it is still empty: once it has promotions, assignments or attendance it is
     * history and stays. The active year cannot be deleted either, because the whole app hangs off it.
     */
    public function destroy(Request $request, AcademicYear $academicYear): JsonResponse|RedirectResponse
    {
        if ($academicYear->is_active) {
            return $this->modalRejected(
                $request,
                "Tahun ajaran {$academicYear->year_name} sedang aktif dan tidak bisa dihapus. Buat tahun ajaran baru terlebih dahulu jika ingin menggantinya.",
                route('admin.tahun-ajaran.index'),
            );
        }

        $counts = [
            'penugasan wali kelas' => $academicYear->homeroomTeachers()->count(),
            'mata pelajaran' => $academicYear->subjects()->count(),
            'riwayat kenaikan kelas' => $academicYear->promotions()->count(),
        ];
        $used = collect($counts)->filter()->map(fn (int $total, string $label): string => "{$total} {$label}");

        if ($used->isNotEmpty()) {
            return $this->modalRejected(
                $request,
                "Tahun ajaran {$academicYear->year_name} tidak bisa dihapus karena masih menyimpan ".$used->implode(' dan ').'. Data tahun ajaran lama disimpan sebagai histori.',
                route('admin.tahun-ajaran.index'),
            );
        }

        $yearName = $academicYear->year_name;
        $academicYear->delete();

        return $this->modalSaved($request, "Tahun ajaran {$yearName} berhasil dihapus.", route('admin.tahun-ajaran.index'));
    }

    /**
     * Payload for the "Tahun Ajaran Baru" modal: suggested name, and the unfinished steps of the active year that must be acknowledged.
     */
    public function create(Request $request): JsonResponse|RedirectResponse
    {
        $current = AcademicYear::current();
        $progress = $current?->setupProgress();
        $suggestedName = $current
            ? ($current->startYear() + 1).'/'.($current->startYear() + 2)
            : (now()->month >= 7 ? now()->year : now()->year - 1).'/'.(now()->month >= 7 ? now()->year + 1 : now()->year);

        return $this->modalForm($request, [
            'confirm' => $current ? "Buat tahun ajaran baru dan nonaktifkan {$current->year_name}?" : null,
            'values' => [
                'year_name' => $suggestedName,
                'current_note' => $current
                    ? "Tahun ajaran baru otomatis menjadi satu-satunya yang aktif. {$current->year_name} akan dinonaktifkan dan tersimpan sebagai histori."
                    : 'Tahun ajaran pertama. Tahun ajaran baru otomatis aktif.',
            ],
            'slots' => [
                'incomplete' => view('pages.admin.academic-years.incomplete', ['current' => $current, 'progress' => $progress])->render(),
            ],
        ], route('admin.tahun-ajaran.index'));
    }

    /**
     * Creating a year makes it the only active one; the previous year stays as read-only history.
     */
    public function store(AcademicYearRequest $request): JsonResponse|RedirectResponse
    {
        $previous = AcademicYear::current();

        $academicYear = DB::transaction(function () use ($request): AcademicYear {
            $academicYear = AcademicYear::create(['year_name' => $request->validated('year_name')]);
            $academicYear->activate();

            return $academicYear;
        });

        $message = e("Tahun ajaran {$academicYear->year_name} dibuat dan diaktifkan.");

        if ($previous) {
            $message .= ' '.e("{$previous->year_name} sekarang nonaktif (tersimpan sebagai histori).")
                .'<br><a href="'.e(route('admin.kenaikan.index')).'">Lanjutkan dengan proses kenaikan kelas →</a>';
        }

        return $this->modalSaved($request, [
            'icon' => 'success',
            'title' => 'Tahun ajaran aktif',
            'html' => $message,
            'toast' => false,
        ], route('admin.tahun-ajaran.index'));
    }
}
