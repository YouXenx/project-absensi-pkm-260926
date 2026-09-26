<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\PromotionResult;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates one promotion batch for a source class.
 *
 * decisions[student_id] is one of:
 *   "skip"     – leave for later (not processed now)
 *   "stay"     – tinggal kelas (stays in the source class)
 *   "graduate" – lulus (keeps class_id, status becomes "lulus")
 *   {class_id} – naik ke kelas tersebut
 */
class PromotionRequest extends FormRequest
{
    public const SKIP = 'skip';

    public const STAY = 'stay';

    public const GRADUATE = 'graduate';

    /**
     * @var Collection<int, array{student: Student, result: PromotionResult, toClass: SchoolClass|null}>|null
     */
    private ?Collection $plan = null;

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
        return [
            'from_class_id' => ['required', 'integer', Rule::exists(SchoolClass::class, 'class_id')],
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_class_id.required' => 'Pilih kelas asal.',
            'decisions.required' => 'Tidak ada siswa yang bisa diproses di kelas ini.',
        ];
    }

    public function academicYear(): AcademicYear
    {
        return $this->attributes->get('activeAcademicYear') ?? AcademicYear::current();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fromClassId = $this->integer('from_class_id');
                $decisions = collect($this->input('decisions'))->mapWithKeys(fn ($value, $key): array => [(int) $key => (string) $value]);

                $students = Student::query()
                    ->active()
                    ->where('class_id', $fromClassId)
                    ->whereKey($decisions->keys())
                    ->whereDoesntHave('promotions', fn ($query) => $query->where('academic_year_id', $this->academicYear()->academic_year_id))
                    ->get()
                    ->keyBy('student_id');

                if ($students->count() !== $decisions->count()) {
                    $validator->errors()->add('decisions', 'Sebagian siswa tidak lagi ada di kelas asal atau sudah diproses di tahun ajaran ini. Muat ulang halaman.');

                    return;
                }

                $classIds = $decisions->reject(fn (string $value): bool => in_array($value, [self::SKIP, self::STAY, self::GRADUATE], true))->unique();
                $targetClasses = SchoolClass::query()->whereKey($classIds->all())->get()->keyBy('class_id');

                $plan = collect();

                foreach ($decisions as $studentId => $value) {
                    if ($value === self::SKIP) {
                        continue;
                    }

                    $student = $students[$studentId];

                    $plan->push(match (true) {
                        $value === self::STAY => ['student' => $student, 'result' => PromotionResult::Retained, 'toClass' => $student->schoolClass],
                        $value === self::GRADUATE => ['student' => $student, 'result' => PromotionResult::Graduated, 'toClass' => null],
                        ctype_digit($value) && isset($targetClasses[(int) $value]) && (int) $value !== $fromClassId => ['student' => $student, 'result' => PromotionResult::Promoted, 'toClass' => $targetClasses[(int) $value]],
                        default => null,
                    });

                    if ($plan->last() === null) {
                        $validator->errors()->add('decisions', "Pilihan untuk {$student->name} tidak valid.");

                        return;
                    }
                }

                if ($plan->isEmpty()) {
                    $validator->errors()->add('decisions', 'Pilih minimal satu siswa untuk diproses (semua siswa berstatus "lewati").');

                    return;
                }

                $this->plan = $plan->sortBy(fn (array $row): string => $row['student']->name)->values();
            },
        ];
    }

    /**
     * Validated plan: what happens to each selected student.
     *
     * @return Collection<int, array{student: Student, result: PromotionResult, toClass: SchoolClass|null}>
     */
    public function plan(): Collection
    {
        return $this->plan ?? collect();
    }
}
