<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
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
        /** @var SchoolClass|null $schoolClass */
        $schoolClass = $this->route('schoolClass');

        return [
            'class_name' => [
                'required', 'string', 'max:50',
                Rule::unique(SchoolClass::class, 'class_name')->ignore($schoolClass?->class_id, 'class_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'class_name' => 'nama kelas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_name.required' => 'Nama kelas wajib diisi.',
            'class_name.unique' => 'Nama kelas sudah digunakan.',
            'class_name.max' => 'Nama kelas maksimal :max karakter.',
        ];
    }
}
