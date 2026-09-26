<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Individual class transfer outside the yearly promotion process.
 */
class StudentTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'class_id' => [
                'required', 'integer',
                Rule::exists(SchoolClass::class, 'class_id'),
                Rule::notIn([$student->class_id]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_id.required' => 'Pilih kelas tujuan.',
            'class_id.exists' => 'Kelas tujuan tidak ditemukan.',
            'class_id.not_in' => 'Kelas tujuan sama dengan kelas saat ini.',
        ];
    }
}
