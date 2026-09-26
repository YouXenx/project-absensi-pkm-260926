<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AcademicYearRequest extends FormRequest
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
        return [
            'year_name' => [
                'bail', 'required', 'string', 'regex:/^\d{4}\/\d{4}$/',
                Rule::unique(AcademicYear::class, 'year_name'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    [$start, $end] = array_map('intval', explode('/', $value));

                    if ($end !== $start + 1) {
                        $fail('Tahun kedua harus satu tahun setelah tahun pertama, contoh 2026/2027.');
                    }
                },
            ],
            'acknowledge_incomplete' => ['nullable', 'boolean'],
        ];
    }

    /**
     * A new year must come after the active one, and an unfinished active year needs explicit acknowledgement.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $current = AcademicYear::current();

                if (! $current || $validator->errors()->has('year_name')) {
                    return;
                }

                if ($this->string('year_name')->toString() <= $current->year_name) {
                    $validator->errors()->add('year_name', "Tahun ajaran baru harus setelah tahun ajaran aktif ({$current->year_name}).");

                    return;
                }

                if (! $current->setupProgress()['complete'] && ! $this->boolean('acknowledge_incomplete')) {
                    $validator->errors()->add(
                        'acknowledge_incomplete',
                        "Proses tahun ajaran {$current->year_name} belum selesai. Centang konfirmasi jika tetap ingin membuat tahun ajaran baru.",
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
            'year_name.required' => 'Nama tahun ajaran wajib diisi.',
            'year_name.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh 2026/2027.',
            'year_name.unique' => 'Tahun ajaran ini sudah ada.',
        ];
    }
}
