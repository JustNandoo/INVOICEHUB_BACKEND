<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fullName' => trim((string) $this->input('fullName')),
            'businessName' => trim((string) $this->input('businessName')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fullName' => ['bail', 'required', 'string', 'min:2', 'max:100'],
            'email' => [
                'bail',
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'businessName' => ['bail', 'required', 'string', 'min:2', 'max:150'],
            'password' => ['bail', 'required', 'string', Password::defaults()],
            'termsAccepted' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fullName.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'businessName.required' => 'Nama usaha wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'termsAccepted.required' => 'Persetujuan syarat dan kebijakan privasi wajib diisi.',
            'termsAccepted.accepted' => 'Anda harus menyetujui Syarat & Ketentuan dan Kebijakan Privasi.',
        ];
    }
}
