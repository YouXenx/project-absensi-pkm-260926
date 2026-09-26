<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin-only management of guru accounts. An account that already teaches (mapel/wali kelas) is kept and
 * deactivated instead of deleted, so the history stays readable; an unused account can be removed for good.
 * Create/edit happen in the modal on the index page.
 */
class TeacherController extends Controller implements HasMiddleware
{
    use RespondsToModalForms;

    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    public function index(): View
    {
        return view('pages.admin.teachers.index');
    }

    /**
     * DataTables server-side endpoint. Optional "status" = active|inactive.
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $query = User::teachers()
            ->when($request->input('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('is_active', false));

        return $request->respond(
            query: $query,
            searchable: ['name', 'email'],
            sortable: [
                'name' => 'name',
                'email' => 'email',
                'status' => 'is_active',
                'created_at' => 'created_at',
            ],
            transform: fn (User $teacher): array => [
                'name' => e($teacher->name),
                'email' => e($teacher->email),
                'status' => view('partials.status-badge', ['isActive' => $teacher->is_active])->render(),
                'created_at' => $teacher->created_at?->format('d/m/Y') ?? '-',
                'actions' => view('pages.admin.teachers.actions', ['teacher' => $teacher])->render(),
            ],
            defaultOrder: 'name',
        );
    }

    public function store(TeacherRequest $request): JsonResponse|RedirectResponse
    {
        $teacher = User::create([
            ...$request->teacherData(),
            'role' => UserRole::Guru,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->modalSaved($request, "Akun guru {$teacher->name} berhasil dibuat.", route('admin.guru.index'));
    }

    /**
     * Payload for the edit modal.
     */
    public function edit(Request $request, User $teacher): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-teacher', $teacher);

        return $this->modalForm($request, [
            'action' => route('admin.guru.update', $teacher),
            'method' => 'PUT',
            'title' => "Edit {$teacher->name}",
            'values' => [
                'name' => $teacher->name,
                'email' => $teacher->email,
                'is_active' => $teacher->is_active,
            ],
        ], route('admin.guru.index'));
    }

    public function update(TeacherRequest $request, User $teacher): JsonResponse|RedirectResponse
    {
        $teacher->update($request->teacherData());

        return $this->modalSaved($request, "Akun guru {$teacher->name} berhasil diperbarui.", route('admin.guru.index'));
    }

    /**
     * Replace a forgotten password with a random temporary one, shown once to the admin.
     */
    public function resetPassword(Request $request, User $teacher): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-teacher', $teacher);

        $temporaryPassword = Str::password(10, symbols: false);

        $teacher->forceFill([
            'password' => $temporaryPassword,
            'remember_token' => Str::random(60),
        ])->save();

        return $this->modalSaved($request, [
            'icon' => 'success',
            'title' => 'Password direset',
            'html' => 'Password sementara untuk <strong>'.e($teacher->name).'</strong>:'
                .'<div style="font:600 20px/1.4 \'JetBrains Mono\',monospace;margin:14px 0;padding:10px;border-radius:8px;background:var(--bg-muted)" data-testid="temporary-password">'.e($temporaryPassword).'</div>'
                .'Sampaikan ke guru yang bersangkutan. Password ini hanya ditampilkan sekali; guru dapat menggantinya di menu Profil Saya.',
            'toast' => false,
        ], route('admin.guru.index'));
    }

    /**
     * A guru account is only removed when nothing points at it: assignments (mapel, wali kelas) must stay
     * readable as history, so an account that has them can only be deactivated.
     */
    public function destroy(Request $request, User $teacher): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-teacher', $teacher);

        $subjectCount = $teacher->subjects()->count();
        $homeroomCount = $teacher->homeroomAssignments()->count();

        if ($subjectCount > 0 || $homeroomCount > 0) {
            $reasons = collect([
                $subjectCount > 0 ? "{$subjectCount} penugasan mapel" : null,
                $homeroomCount > 0 ? "{$homeroomCount} penugasan wali kelas" : null,
            ])->filter()->implode(' dan ');

            return $this->modalRejected(
                $request,
                "Akun {$teacher->name} tidak bisa dihapus karena masih memiliki {$reasons} yang tersimpan sebagai histori. Nonaktifkan akunnya agar tidak bisa login.",
                route('admin.guru.index'),
            );
        }

        $name = $teacher->name;
        $teacher->delete();

        return $this->modalSaved($request, "Akun guru {$name} berhasil dihapus.", route('admin.guru.index'));
    }

    /**
     * Activate or deactivate a guru account. A deactivated guru is signed out on their next request.
     */
    public function updateStatus(Request $request, User $teacher): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-teacher', $teacher);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $teacher->update(['is_active' => (bool) $validated['is_active']]);

        $message = $teacher->is_active
            ? "Akun guru {$teacher->name} diaktifkan kembali."
            : "Akun guru {$teacher->name} dinonaktifkan.";

        return $this->modalSaved($request, $message, route('admin.guru.index'));
    }
}
