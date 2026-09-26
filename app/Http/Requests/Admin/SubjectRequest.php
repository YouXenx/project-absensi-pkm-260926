<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create: one subject + one guru assigned to one or more classes (class_ids[]).
 * Edit: a single assignment (class_id).
 */
class SubjectRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $this->merge(['subject_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('subject_name')))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $classRules = ['integer', Rule::exists(SchoolClass::class, 'class_id')];

        return [
            'subject_name' => ['required', 'string', 'max:100'],
            'user_id' => [
                'required', 'integer',
                Rule::exists(User::class, 'id')->where('role', UserRole::Guru->value)->where('is_active', true),
            ],
            ...($this->isEditing()
                ? ['class_id' => ['required', ...$classRules]]
                : ['class_ids' => ['required', 'array', 'min:1'], 'class_ids.*' => ['required', 'distinct', ...$classRules]]),
        ];
    }

    /**
     * The same subject name may exist only once per class per academic year.
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

                $classIds = $this->isEditing() ? [$this->integer('class_id')] : array_map('intval', $this->input('class_ids'));
                $field = $this->isEditing() ? 'class_id' : 'class_ids';

                // Step 3 before step 4: a class needs its homeroom teacher for the active year first.
                $withoutHomeroom = SchoolClass::query()
                    ->whereKey($classIds)
                    ->whereDoesntHave('homeroomTeachers', fn ($query) => $query->where('academic_year_id', $this->academicYear()->academic_year_id))
                    ->orderBy('class_name')
                    ->pluck('class_name');

                if ($withoutHomeroom->isNotEmpty()) {
                    $validator->errors()->add(
                        $field,
                        "Tetapkan wali kelas tahun ajaran {$this->academicYear()->year_name} terlebih dahulu untuk: ".$withoutHomeroom->implode(', ').'.',
                    );

                    return;
                }

                $conflicts = Subject::query()
                    ->with('schoolClass')
                    ->where('academic_year_id', $this->academicYear()->academic_year_id)
                    ->where('subject_name', $this->input('subject_name'))
                    ->whereIn('class_id', $classIds)
                    ->when($this->isEditing(), fn ($query) => $query->whereKeyNot($this->route('subject')->subject_id))
                    ->get();

                if ($conflicts->isNotEmpty()) {
                    $validator->errors()->add(
                        $field,
                        "Mapel {$this->input('subject_name')} sudah ada di tahun ajaran {$this->academicYear()->year_name} untuk: ".$conflicts->pluck('schoolClass.class_name')->sort()->implode(', ').'.',
                    );
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
            'subject_name.required' => 'Nama mata pelajaran wajib diisi.',
            'subject_name.max' => 'Nama mata pelajaran maksimal :max karakter.',
            'user_id.required' => 'Pilih guru pengampu.',
            'user_id.exists' => 'Guru pengampu harus akun guru yang aktif.',
            'class_id.required' => 'Pilih kelas.',
            'class_ids.required' => 'Pilih minimal satu kelas.',
            'class_ids.min' => 'Pilih minimal satu kelas.',
        ];
    }

    public function isEditing(): bool
    {
        return $this->route('subject') instanceof Subject;
    }
}
