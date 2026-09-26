<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeroomTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    public function academicYear(): AcademicYear
    {
        return $this->attributes->get('activeAcademicYear') ?? AcademicYear::current();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var HomeroomTeacher|null $homeroom */
        $homeroom = $this->route('homeroomTeacher');

        return [
            'class_id' => [
                'required', 'integer',
                Rule::exists(SchoolClass::class, 'class_id'),
                Rule::unique(HomeroomTeacher::class, 'class_id')
                    ->where('academic_year_id', $this->academicYear()->academic_year_id)
                    ->ignore($homeroom?->homeroom_id, 'homeroom_id'),
            ],
            'user_id' => [
                'required', 'integer',
                Rule::exists(User::class, 'id')->where('role', UserRole::Guru->value)->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_id.required' => 'Pilih kelas.',
            'class_id.exists' => 'Kelas tidak ditemukan.',
            'class_id.unique' => "Kelas ini sudah punya wali kelas di tahun ajaran {$this->academicYear()->year_name}.",
            'user_id.required' => 'Pilih guru.',
            'user_id.exists' => 'Guru harus akun guru yang aktif.',
        ];
    }
}
