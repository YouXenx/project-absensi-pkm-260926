<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Updates the signed-in user's own account. There is no id in the URL, so one
 * user can never target another user's profile.
 */
class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()->id),
            ],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.lowercase' => 'Email harus huruf kecil.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'current_password.required_with' => 'Masukkan password saat ini untuk mengganti password.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal :min karakter.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $data = $this->safe()->only(['name', 'email']);

        if ($this->filled('password')) {
            $data['password'] = $this->validated('password');
        }

        return $data;
    }
}
