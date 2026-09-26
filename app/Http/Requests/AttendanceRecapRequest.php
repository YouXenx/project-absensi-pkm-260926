<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceRecapRequest extends FormRequest
{
    public const CUSTOM_RANGE = 'custom';

    /**
     * Determine if the user is authorized to make this request. Data scoping happens in the query.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'in:1,2,'.self::CUSTOM_RANGE],
            'date_from' => ['nullable', 'required_if:semester,'.self::CUSTOM_RANGE, 'date'],
            'date_to' => ['nullable', 'required_if:semester,'.self::CUSTOM_RANGE, 'date', 'after_or_equal:date_from'],
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required_if' => 'Isi tanggal mulai untuk rentang tanggal kustom.',
            'date_to.required_if' => 'Isi tanggal selesai untuk rentang tanggal kustom.',
            'date_to.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }
}
