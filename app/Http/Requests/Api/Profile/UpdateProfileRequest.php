<?php

namespace App\Http\Requests\Api\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['fullName', 'businessName', 'whatsapp', 'city'] as $field) {
            if ($this->has($field)) {
                $values[$field] = trim((string) $this->input($field));
            }
        }
        if ($this->has('email')) {
            $values['email'] = mb_strtolower(trim((string) $this->input('email')));
        }
        if ($this->has('businessType')) {
            $values['businessType'] = mb_strtolower(trim((string) $this->input('businessType')));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'fullName' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')->ignore($this->user()->id)],
            'businessName' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'businessType' => ['sometimes', 'nullable', Rule::in(['reseller', 'retail', 'service', 'manufacturing', 'other'])],
            'currentPassword' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('email') || $this->input('email') === $this->user()->email) {
                return;
            }

            if (! $this->filled('currentPassword')) {
                $validator->errors()->add('currentPassword', 'Current password is required to change your email.');
            } elseif (! Hash::check((string) $this->input('currentPassword'), $this->user()->password)) {
                $validator->errors()->add('currentPassword', 'Current password is incorrect.');
            }
        });
    }
}
