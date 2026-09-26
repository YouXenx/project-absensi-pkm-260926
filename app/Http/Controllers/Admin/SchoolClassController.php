<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolClassRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Data Kelas. Create/edit happen in the modal on the index page.
 */
class SchoolClassController extends Controller implements HasMiddleware
{
    use RespondsToModalForms;

    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    public function index(): View
    {
        return view('pages.admin.classes.index');
    }

    /**
     * DataTables server-side endpoint: returns only the requested page.
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        return $request->respond(
            query: SchoolClass::query()->withCount(['students' => fn ($query) => $query->active()]),
            searchable: ['class_name'],
            sortable: [
                'class_name' => 'class_name',
                'students_count' => 'students_count',
            ],
            transform: fn (SchoolClass $schoolClass): array => [
                'class_name' => e($schoolClass->class_name),
                'students_count' => $schoolClass->students_count,
                'actions' => view('partials.datatable-actions', [
                    'modal' => 'class-modal',
                    'editUrl' => route('admin.kelas.edit', $schoolClass),
                    'deleteUrl' => route('admin.kelas.destroy', $schoolClass),
                    'deleteMessage' => "Hapus {$schoolClass->class_name}?",
                ])->render(),
            ],
            defaultOrder: 'class_name',
        );
    }

    public function store(SchoolClassRequest $request): JsonResponse|RedirectResponse
    {
        $schoolClass = SchoolClass::create($request->validated());

        return $this->modalSaved($request, "{$schoolClass->class_name} berhasil ditambahkan.", route('admin.kelas.index'));
    }

    /**
     * Payload for the edit modal.
     */
    public function edit(Request $request, SchoolClass $schoolClass): JsonResponse|RedirectResponse
    {
        return $this->modalForm($request, [
            'action' => route('admin.kelas.update', $schoolClass),
            'method' => 'PUT',
            'title' => "Edit {$schoolClass->class_name}",
            'values' => ['class_name' => $schoolClass->class_name],
        ], route('admin.kelas.index'));
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass): JsonResponse|RedirectResponse
    {
        $schoolClass->update($request->validated());

        return $this->modalSaved($request, "{$schoolClass->class_name} berhasil diperbarui.", route('admin.kelas.index'));
    }

    /**
     * Classes with students (active or graduated), subjects or promotion history cannot be removed (foreign keys restrict on delete).
     * Homeroom assignments are removed with the class (cascade).
     */
    public function destroy(Request $request, SchoolClass $schoolClass): JsonResponse|RedirectResponse
    {
        $studentCount = $schoolClass->students()->count();
        $subjectCount = $schoolClass->subjects()->count();

        $hasPromotionHistory = $schoolClass->hasPromotionHistory();

        if ($studentCount > 0 || $subjectCount > 0 || $hasPromotionHistory) {
            $reasons = collect([
                $studentCount > 0 ? "{$studentCount} siswa" : null,
                $subjectCount > 0 ? "{$subjectCount} mapel" : null,
                $hasPromotionHistory ? 'histori kenaikan kelas' : null,
            ])->filter()->implode(' dan ');

            return $this->modalRejected($request, "{$schoolClass->class_name} tidak bisa dihapus karena masih memiliki {$reasons}.", route('admin.kelas.index'));
        }

        $schoolClass->delete();

        return $this->modalSaved($request, "{$schoolClass->class_name} berhasil dihapus.", route('admin.kelas.index'));
    }
}
