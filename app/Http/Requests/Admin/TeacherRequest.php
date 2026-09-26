<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $teacher */
        $teacher = $this->route('teacher');

        return $teacher
            ? $this->user()->can('manage-teacher', $teacher)
            : $this->user()->can('access-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     * The password is required when creating and optional when editing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $teacher */
        $teacher = $this->route('teacher');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($teacher?->id),
            ],
            'password' => [$teacher ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama guru wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.lowercase' => 'Email harus huruf kecil.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal :min karakter.',
        ];
    }

    /**
     * Validated data ready for the model: blank password on edit means "keep current".
     *
     * @return array<string, mixed>
     */
    public function teacherData(): array
    {
        $data = $this->safe()->only(['name', 'email']);

        if ($this->filled('password')) {
            $data['password'] = $this->validated('password');
        }

        if ($this->has('is_active')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
