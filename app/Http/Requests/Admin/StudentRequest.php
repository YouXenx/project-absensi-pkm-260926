<?php

namespace App\Http\Requests\Admin;

use App\Gender;
use App\Models\SchoolClass;
use App\Models\Student;
use App\StudentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
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
        /** @var Student|null $student */
        $student = $this->route('student');

        return [
            'nis' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                Rule::unique(Student::class, 'nis')->ignore($student?->student_id, 'student_id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Only set on create; moving an existing student uses the transfer action.
            'class_id' => [$student ? 'exclude' : 'required', 'integer', Rule::exists(SchoolClass::class, 'class_id')],
            'gender' => ['required', Rule::enum(Gender::class)],
            'status' => ['sometimes', 'required', Rule::enum(StudentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis.required' => 'NIS wajib diisi.',
            'nis.regex' => 'NIS hanya boleh berisi angka.',
            'nis.max' => 'NIS maksimal :max digit.',
            'nis.unique' => 'NIS sudah terdaftar.',
            'name.required' => 'Nama siswa wajib diisi.',
            'class_id.required' => 'Kelas wajib dipilih.',
            'class_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
        ];
    }
}
