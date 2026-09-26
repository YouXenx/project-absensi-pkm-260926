<?php

namespace App\Http\Requests\Guru;

use App\AttendanceStatus;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Attendance input by a guru for one subject, always for today.
 *
 * Payload: date (must be today), attendance[student_id][status|description].
 * The "one student" route validates only that student's row; the bulk route requires every row.
 */
class TakeAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        // inspect() keeps the policy reason (e.g. subject of an old academic year) for the SweetAlert.
        return Gate::forUser($this->user())->inspect('take-attendance', $this->subject());
    }

    public function subject(): Subject
    {
        return $this->route('subject');
    }

    public function singleStudent(): ?Student
    {
        return $this->route('student');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rowKey = $this->singleStudent() ? 'attendance.'.$this->singleStudent()->student_id : 'attendance.*';

        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::in([now()->toDateString()])],
            'attendance' => ['required', 'array'],
            "{$rowKey}.status" => ['required', Rule::enum(AttendanceStatus::class)],
            "{$rowKey}.description" => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Every submitted student must be an active student of the subject's class.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $submittedIds = $this->rows()->keys();
                $rosterIds = $this->subject()->schoolClass->activeStudents()->whereKey($submittedIds)->pluck('student_id');

                if ($rosterIds->count() !== $submittedIds->count()) {
                    $validator->errors()->add('attendance', 'Ada siswa yang bukan anggota kelas ini. Muat ulang halaman absensi.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.in' => 'Guru hanya bisa mengisi atau mengubah absensi untuk tanggal hari ini ('.now()->translatedFormat('d/m/Y').'). Muat ulang halaman.',
            'date.required' => 'Tanggal absensi tidak ditemukan. Muat ulang halaman.',
            'attendance.required' => 'Tidak ada data absensi yang dikirim.',
            'attendance.*.status.required' => 'Pilih status kehadiran untuk semua siswa (gunakan "Tandai semua hadir" lalu ubah yang tidak hadir).',
            'attendance.*.status.enum' => 'Status kehadiran tidak valid.',
            'attendance.*.description.max' => 'Keterangan maksimal :max karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->singleStudent()) {
            return;
        }

        // A per-row "Simpan" button posts the whole form; keep only that student's row.
        $studentId = $this->singleStudent()->student_id;
        $this->merge(['attendance' => [$studentId => $this->input("attendance.{$studentId}", [])]]);
    }

    /**
     * @return Collection<int, array{status: string, description: string|null}>
     */
    public function rows(): Collection
    {
        return collect($this->input('attendance', []))
            ->mapWithKeys(fn (array $row, int|string $studentId): array => [(int) $studentId => [
                'status' => $row['status'] ?? '',
                'description' => filled($row['description'] ?? null) ? trim($row['description']) : null,
            ]]);
    }
}
