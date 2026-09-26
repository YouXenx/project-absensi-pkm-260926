<?php

namespace App\Http\Requests\Admin;

use App\StudentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marking a student as moved to another school, dropped out, graduated or active again.
 * The record itself is kept: attendance history must stay readable.
 */
class StudentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Access is already limited to admins by the "admin" route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status siswa.',
            'note.max' => 'Keterangan maksimal :max karakter.',
        ];
    }

    public function status(): StudentStatus
    {
        return StudentStatus::from($this->validated('status'));
    }
}
