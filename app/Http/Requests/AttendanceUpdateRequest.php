<?php

namespace App\Http\Requests;

use App\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Editing one attendance record. The policy decides who may: admins any date, a guru only today
 * and only for their own subjects. The date, student and subject themselves never change.
 */
class AttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        /** @var Attendance $attendance */
        $attendance = $this->route('attendance');

        // inspect() keeps the policy reason (past date) for the SweetAlert.
        return Gate::forUser($this->user())->inspect('update', $attendance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status kehadiran.',
            'status.enum' => 'Status kehadiran tidak valid.',
            'description.max' => 'Keterangan maksimal :max karakter.',
        ];
    }
}
