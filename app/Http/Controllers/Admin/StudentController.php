<?php

namespace App\Http\Controllers\Admin;

use App\Gender;
use App\Http\Controllers\Concerns\RespondsToModalForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentRequest;
use App\Http\Requests\Admin\StudentStatusRequest;
use App\Http\Requests\Admin\StudentTransferRequest;
use App\Http\Requests\DataTableRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Data Siswa. Create, edit and pindah kelas happen in modals on the index page.
 */
class StudentController extends Controller implements HasMiddleware
{
    use RespondsToModalForms;

    public static function middleware(): array
    {
        return [new Middleware('can:access-admin')];
    }

    public function index(): View
    {
        return view('pages.admin.students.index', [
            'classes' => SchoolClass::orderBy('class_name')->get(['class_id', 'class_name']),
            'genders' => Gender::cases(),
            'statuses' => StudentStatus::cases(),
        ]);
    }

    /**
     * DataTables server-side endpoint. Optional "class_id" and "status" (aktif|pindah|lulus|keluar) filters.
     */
    public function data(DataTableRequest $request): JsonResponse
    {
        $status = StudentStatus::tryFrom((string) $request->input('status'));

        $query = Student::query()
            ->join('classes', 'classes.class_id', '=', 'students.class_id')
            ->select('students.*', 'classes.class_name')
            ->when($request->integer('class_id'), fn ($query, int $classId) => $query->where('students.class_id', $classId))
            ->when($status, fn ($query, StudentStatus $status) => $query->where('students.status', $status));

        return $request->respond(
            query: $query,
            searchable: ['students.nis', 'students.name', 'classes.class_name'],
            sortable: [
                'nis' => 'students.nis',
                'name' => 'students.name',
                'class_name' => 'classes.class_name',
                'gender' => 'students.gender',
                'status' => 'students.status',
            ],
            transform: fn (Student $student): array => [
                'nis' => e($student->nis),
                'name' => e($student->name),
                'class_name' => e($student->class_name),
                'gender' => $student->gender->label(),
                'status' => view('partials.student-status', ['status' => $student->status])->render(),
                'actions' => view('partials.datatable-actions', [
                    'modal' => 'student-modal',
                    'extraActions' => [
                        ...($student->status->isEnrolled() ? [[
                            'modal' => 'transfer-modal',
                            'url' => route('admin.siswa.pindah', $student),
                            'label' => 'Pindah kelas',
                            'icon' => '<path d="M17 3l4 4-4 4"/><path d="M3 7h18"/><path d="M7 21l-4-4 4-4"/><path d="M21 17H3"/>',
                        ]] : []),
                        [
                            'modal' => 'status-modal',
                            'url' => route('admin.siswa.status', $student),
                            'label' => 'Ubah status siswa',
                            'icon' => '<path d="M16 3h5v5"/><path d="M21 3 10 14"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
                        ],
                    ],
                    'editUrl' => route('admin.siswa.edit', $student),
                    'deleteUrl' => route('admin.siswa.destroy', $student),
                    'deleteMessage' => "Hapus siswa {$student->name}?",
                ])->render(),
            ],
            defaultOrder: 'students.name',
        );
    }

    public function store(StudentRequest $request): JsonResponse|RedirectResponse
    {
        $student = Student::create($request->validated());

        return $this->modalSaved($request, "Siswa {$student->name} berhasil ditambahkan.", route('admin.siswa.index'));
    }

    /**
     * Payload for the edit modal. The class is shown read-only: moving a student goes through transfer().
     */
    public function edit(Request $request, Student $student): JsonResponse|RedirectResponse
    {
        return $this->modalForm($request, [
            'action' => route('admin.siswa.update', $student),
            'method' => 'PUT',
            'title' => "Edit {$student->name}",
            'values' => [
                'nis' => $student->nis,
                'name' => $student->name,
                'gender' => $student->gender->value,
                'status' => $student->status->value,
                'current_class' => $student->schoolClass->class_name,
                'class_note' => $student->status->isEnrolled()
                    ? 'Untuk memindahkan siswa gunakan tombol Pindah Kelas di tabel.'
                    : "Status siswa: {$student->status->label()}; kelas terakhir tidak diubah.",
            ],
        ], route('admin.siswa.index'));
    }

    /**
     * Class changes go through transfer() so they are explicit and confirmed; the edit form cannot move a student.
     */
    public function update(StudentRequest $request, Student $student): JsonResponse|RedirectResponse
    {
        $student->update($request->safe()->except('class_id'));

        return $this->modalSaved($request, "Data siswa {$student->name} berhasil diperbarui.", route('admin.siswa.index'));
    }

    /**
     * Payload for the "Pindah Kelas" modal: target classes with their active student counts.
     */
    public function transferForm(Request $request, Student $student): JsonResponse|RedirectResponse
    {
        if (! $student->status->isEnrolled()) {
            return $this->modalRejected($request, $this->inactiveTransferMessage($student), route('admin.siswa.index'));
        }

        $classes = SchoolClass::query()
            ->withCount(['students' => fn ($query) => $query->active()])
            ->whereKeyNot($student->class_id)
            ->orderBy('class_name')
            ->get();

        return $this->modalForm($request, [
            'action' => route('admin.siswa.pindah.update', $student),
            'method' => 'PATCH',
            'title' => "Pindah Kelas: {$student->name}",
            'confirm' => "Pindahkan {$student->name} dari {$student->schoolClass->class_name} ke kelas yang dipilih?",
            'values' => [
                'nis' => $student->nis,
                'current_class' => $student->schoolClass->class_name,
            ],
            'slots' => [
                'target_classes' => view('pages.admin.students.transfer-options', ['classes' => $classes])->render(),
            ],
        ], route('admin.siswa.index'));
    }

    /**
     * Individual class transfer (pindah kelas) outside the yearly promotion. Past attendance stays linked
     * to the old class through its subjects, so history is unaffected.
     */
    public function transfer(StudentTransferRequest $request, Student $student): JsonResponse|RedirectResponse
    {
        if (! $student->status->isEnrolled()) {
            return $this->modalRejected($request, $this->inactiveTransferMessage($student), route('admin.siswa.index'));
        }

        $fromClass = $student->schoolClass;
        $student->update(['class_id' => $request->validated('class_id')]);
        $toClass = $student->fresh('schoolClass')->schoolClass;

        return $this->modalSaved($request, [
            'icon' => 'success',
            'title' => 'Siswa dipindahkan',
            'text' => "{$student->name} pindah dari {$fromClass->class_name} ke {$toClass->class_name}.",
            'toast' => false,
        ], route('admin.siswa.index'));
    }

    private function inactiveTransferMessage(Student $student): string
    {
        return "{$student->name} berstatus {$student->status->label()} dan tidak bisa dipindahkan ke kelas lain.";
    }

    /**
     * Payload for the "Ubah status siswa" modal (pindah sekolah, keluar, lulus, atau aktif kembali).
     */
    public function statusForm(Request $request, Student $student): JsonResponse|RedirectResponse
    {
        return $this->modalForm($request, [
            'action' => route('admin.siswa.status.update', $student),
            'method' => 'PATCH',
            'title' => "Ubah status: {$student->name}",
            'values' => [
                'student_name' => $student->name,
                'current_class' => $student->schoolClass->class_name,
                'current_status' => $student->status->label(),
                'status' => $student->status->value,
            ],
        ], route('admin.siswa.index'));
    }

    /**
     * Only the status changes: the student, their class and their attendance stay as history.
     * Students who are not "aktif" drop out of class rosters, attendance and the yearly promotion.
     */
    public function status(StudentStatusRequest $request, Student $student): JsonResponse|RedirectResponse
    {
        $previous = $student->status;
        $status = $request->status();

        $student->update(['status' => $status]);

        $note = filled($request->validated('note')) ? ' Keterangan: '.$request->validated('note').'.' : '';

        return $this->modalSaved($request, [
            'icon' => 'success',
            'title' => 'Status siswa diperbarui',
            'text' => "{$student->name}: {$previous->label()} → {$status->label()}.".$note
                .($status->isEnrolled() ? '' : ' Siswa ini tidak lagi muncul di daftar absensi dan kenaikan kelas, tetapi riwayatnya tetap tersimpan.'),
            'toast' => false,
        ], route('admin.siswa.index'));
    }

    /**
     * Students with attendance records cannot be removed (attendances.student_id restricts on delete).
     */
    public function destroy(Request $request, Student $student): JsonResponse|RedirectResponse
    {
        $attendanceCount = $student->attendances()->count();

        if ($attendanceCount > 0) {
            return $this->modalRejected($request, "Siswa {$student->name} tidak bisa dihapus karena sudah memiliki {$attendanceCount} data absensi. Gunakan tombol Ubah status siswa (pindah/keluar/lulus) agar riwayatnya tetap tersimpan.", route('admin.siswa.index'));
        }

        $student->delete();

        return $this->modalSaved($request, "Siswa {$student->name} berhasil dihapus.", route('admin.siswa.index'));
    }
}
